<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The one scan of the writes a body makes to its variables, each told apart once as the scan records it, and what a
 * by-reference write leaves in the variable it writes. Seeding, the guard pass, ternary narrowing, the walk and the
 * readers of a method's returns all read a body's writes here.
 *
 * @phpstan-type NamedWrite = array{string, Node, Expr, VariableWrite}
 * @phpstan-type NamedWrites = list<NamedWrite>
 * @phpstan-type FormedWrites = list<array{NamedWrite, ByReferenceForm}>
 *
 * @phpstan-import-type ByReferenceForm from AnalysisScope
 *
 * @internal
 */
final class VariableWrites
{
    /**
     * Every local variable write in a statement tree, as the name written, the node writing it and the expression it
     * writes to: an assignment or increment, a `foreach` target or by-reference array, a `catch` variable, `unset()`,
     * `static`, `global`, a by-reference `use`, or an argument to a by-reference parameter of a callee the call names.
     *
     * An assignment, an increment, a `foreach` target and a `use` also count the variable a member or key they write
     * goes through; the rest count just the variable they name or key into, so `unset($o->p)` writes nothing.
     *
     * A nested closure's write reaches the body only for a name it imports by reference, and an arrow function's only
     * through `$this`; a nested class's or a named function's never does.
     *
     * @param  array<Node>  $stmts
     * @param  AnalysisScope|null  $scope  names a call's callee on `$this`, `self`, `static` or `parent` as the body
     *                                     does; null names none of them
     * @return NamedWrites
     */
    public static function of(array $stmts, ?AnalysisScope $scope = null): array
    {
        /** @var NamedWrites $writes */
        $writes = [];

        self::collectInto($stmts, [], $writes, $scope?->declaringClass, $scope?->runtime->class);

        return $writes;
    }

    /**
     * The writes to a variable that can still land once the body reaches an offset: each that does not end before it.
     *
     * The one position-ordered rule both the early-exit guard pass and an inline `@var` read a variable's writes by.
     *
     * @param  NamedWrites  $writes
     * @return NamedWrites
     */
    public static function from(string $name, int $offset, array $writes): array
    {
        return array_values(array_filter(
            $writes,
            fn (array $write): bool => $write[0] === $name && $write[1]->getEndFilePos() >= $offset,
        ));
    }

    /**
     * Every local variable name written anywhere in a statement tree, as of() counts writes, but a by-reference call
     * that keeps the value in some form, whose form the read checks.
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    public static function writtenNames(array $stmts, AnalysisScope $scope): array
    {
        return array_column(
            self::partition(self::of($stmts, $scope), $stmts, $scope)[0],
            0,
        );
    }

    /**
     * Split a body's writes into those that replace a variable's value and the by-reference ones that keep it in some
     * form, each with the form it keeps, its span not yet placed.
     *
     * @param  NamedWrites  $writes
     * @param  array<Node>  $stmts  the body the writes were scanned from
     * @return array{NamedWrites, FormedWrites}
     */
    public static function partition(array $writes, array $stmts, AnalysisScope $scope): array
    {
        $replacing = [];
        $kept = [];

        foreach ($writes as $write) {
            $form = self::byReferenceForm($write[1], $write[2], $writes, $stmts, $scope);

            if ($form === null || $form['effect'] === ByReferenceEffect::Replaces) {
                $replacing[] = $write;
            } else {
                $kept[] = [$write, $form];
            }
        }

        return [$replacing, $kept];
    }

    /**
     * What a by-reference argument or a by-reference `foreach` leaves in the variable it writes, or in the member a key
     * path of literal keys names, with the values it adds or writes into an element; null for a write neither makes.
     *
     * @param  NamedWrites  $writes  every write of the body, for those a `foreach` element's variable makes
     * @param  array<Node>  $stmts  the body, for the loops around a `foreach`
     * @return ByReferenceForm|null
     */
    public static function byReferenceForm(Node $node, Expr $target, array $writes, array $stmts, AnalysisScope $scope): ?array
    {
        $path = self::keyPath($target);

        if ($node instanceof Foreach_ && $node->byRef && $node->expr === $target) {
            $values = $path === null ? null : self::foreachElementValues($node, $writes, $stmts);

            return $values === null
                ? self::form(ByReferenceEffect::Replaces)
                : self::form(ByReferenceEffect::WritesElements, $values, $path);
        }

        if (! $node instanceof CallLike) {
            return null;
        }

        if ($path === null) {
            return self::form(ByReferenceEffect::Replaces);
        }

        $effect = resolve(ByReferenceArguments::class)->effect($node, $target);

        return match ($effect) {
            ByReferenceEffect::Appends => self::appendedForm($node, $target, $path),
            ByReferenceEffect::WritesElements, ByReferenceEffect::WritesLeaves => self::walkedForm($node, $effect, $path, $scope),
            default => self::form($effect, [], $path),
        };
    }

    /**
     * The values a by-reference `foreach` puts in the elements of the array it iterates, from each write to its value
     * variable that can land in one; null where that variable is no plain name, or a write is one the scan cannot type.
     *
     * @param  NamedWrites  $writes  every write of the body
     * @param  array<Node>  $stmts  the body, for the loops around the `foreach`
     * @return list<Expr>|null
     */
    public static function foreachElementValues(Foreach_ $node, array $writes, array $stmts): ?array
    {
        $element = $node->valueVar instanceof Variable && is_string($node->valueVar->name) ? $node->valueVar->name : null;

        if ($element === null) {
            return null;
        }

        // After the loop the variable still points at the last element, so a later write lands in it, as one before
        // the loop does when a loop around both runs it again; unset() breaks the reference.
        $loops = new NodeFinder()->find($stmts, fn (Node $loop): bool => $loop !== $node
            && ($loop instanceof For_ || $loop instanceof Foreach_ || $loop instanceof While_ || $loop instanceof Do_)
            && StatementBlocks::encloses($loop, $node));

        return self::elementValues(array_values(array_filter(
            $writes,
            fn (array $write): bool => $write[0] === $element && $write[1] !== $node && ! $write[1] instanceof Unset_
                && ($write[1]->getStartFilePos() > $node->getStartFilePos()
                    || array_any($loops, fn (Node $loop): bool => StatementBlocks::encloses($loop, $write[1]))),
        )));
    }

    /**
     * The variable a target rewrites when it is that variable or a key of it; a property keeps its holder, and a
     * temporary such as a call's result holds no variable.
     */
    public static function rootVariable(Expr $target): ?string
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        return $target instanceof Variable && is_string($target->name) ? $target->name : null;
    }

    /**
     * What a write node does to the variable a target of it names, read once as the scan records the write; the one
     * place writes are told apart.
     */
    private static function writeKind(Node $node, Expr $target): VariableWrite
    {
        return match (true) {
            $node instanceof ClosureExpr => VariableWrite::InClosure,
            $node instanceof AssignRef, $node instanceof Global_, $node instanceof Static_,
            $node instanceof Foreach_ && $node->byRef && $node->valueVar === $target => VariableWrite::References,
            $node instanceof CallLike, $node instanceof Foreach_ && $node->byRef => VariableWrite::ByReference,
            $node instanceof Foreach_ => VariableWrite::Foreach,
            $node instanceof Catch_ => VariableWrite::Catches,
            $node instanceof Unset_ => VariableWrite::Unsets,
            $target instanceof List_, $target instanceof Array_ => VariableWrite::Destructures,
            ! $target instanceof Variable => VariableWrite::MemberOrOffset,
            $node instanceof CoalesceAssign => VariableWrite::Coalesces,
            $node instanceof Assign => VariableWrite::Replaces,
            default => VariableWrite::Compounds,
        };
    }

    /**
     * Walk nodes for the writes that reach the body, tracking the nested closures and arrow functions around each.
     *
     * @param  array<mixed>|Node  $nodes
     * @param  list<ArrowFunction|ClosureExpr>  $enclosing  the nested functions between the body and the nodes
     * @param  NamedWrites  $writes
     * @param  ReflectionClass<object>|null  $declaring
     * @param  ReflectionClass<object>|null  $runtime
     */
    private static function collectInto(
        array|Node $nodes,
        array $enclosing,
        array &$writes,
        ?ReflectionClass $declaring,
        ?ReflectionClass $runtime,
    ): void {
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if (is_array($node)) {
                self::collectInto($node, $enclosing, $writes, $declaring, $runtime);

                continue;
            }

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_) {
                continue;
            }

            foreach (self::writeTargets($node) as $target) {
                foreach (self::writtenVariables($target) as $name) {
                    if (self::reachesBody($name, $enclosing)) {
                        $writes[] = [$name, $node, $target, self::writeKind($node, $target)];
                    }
                }
            }

            foreach (self::rootedWriteTargets($node, $declaring, $runtime) as $target) {
                $name = self::rootVariable($target);

                if ($name !== null && self::reachesBody($name, $enclosing)) {
                    $writes[] = [$name, $node, $target, self::writeKind($node, $target)];
                }
            }

            $inner = $node instanceof ClosureExpr || $node instanceof ArrowFunction
                ? [...$enclosing, $node]
                : $enclosing;

            foreach ($node->getSubNodeNames() as $subNode) {
                $child = $node->{$subNode};

                if ($child instanceof Node || is_array($child)) {
                    // A closure's `use` clause and parameter defaults run in the enclosing scope.
                    $within = in_array($subNode, ['uses', 'params'], true) ? $enclosing : $inner;
                    self::collectInto($child, $within, $writes, $declaring, $runtime);
                }
            }
        }
    }

    /**
     * The expressions an assignment, an increment, a `foreach` target or a by-reference `use` writes to, each counted
     * for the variables writtenVariables() names.
     *
     * @return list<Expr>
     */
    private static function writeTargets(Node $node): array
    {
        $targets = match (true) {
            $node instanceof AssignRef => [$node->var, $node->expr],
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PostInc,
            $node instanceof PreDec, $node instanceof PostDec => [$node->var],
            $node instanceof Foreach_ => [$node->valueVar, $node->keyVar],
            $node instanceof ClosureExpr => array_map(
                fn (ClosureUse $use): Expr => $use->var,
                array_filter($node->uses, fn (ClosureUse $use): bool => $use->byRef),
            ),
            default => [],
        };

        return array_values(array_filter($targets, fn (?Expr $target): bool => $target !== null));
    }

    /**
     * The expressions a `catch`, `unset()`, `static`, `global`, a by-reference `foreach` or a by-reference argument
     * writes to, each counted only for the variable rootVariable() finds.
     *
     * @param  ReflectionClass<object>|null  $declaring
     * @param  ReflectionClass<object>|null  $runtime
     * @return list<Expr>
     */
    private static function rootedWriteTargets(Node $node, ?ReflectionClass $declaring, ?ReflectionClass $runtime): array
    {
        if ($node instanceof CallLike) {
            // Only a call passing a variable or a key of one pays for reflecting its callee.
            $passed = $node->isFirstClassCallable() ? [] : array_filter(
                $node->getArgs(),
                fn (Arg $arg): bool => self::rootVariable($arg->value) !== null,
            );

            return $passed === []
                ? []
                : resolve(ByReferenceArguments::class)->of($node, $passed, $declaring, $runtime);
        }

        $targets = match (true) {
            $node instanceof Foreach_ => $node->byRef ? [$node->expr] : [],
            $node instanceof Catch_ => [$node->var],
            $node instanceof Unset_, $node instanceof Global_ => $node->vars,
            $node instanceof Static_ => array_map(fn (StaticVar $static): Expr => $static->var, $node->vars),
            default => [],
        };

        return array_values(array_filter($targets, fn (?Expr $target): bool => $target !== null));
    }

    /**
     * The names of the variables a write target writes: the variable, each a destructuring list spells, or the one a
     * member or offset write goes through. A variable a key or a member name reads is not written, and neither is the
     * one a variable variable names.
     *
     * @return list<string>
     */
    private static function writtenVariables(Expr $target): array
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            return array_merge(...array_map(
                fn (?ArrayItem $item): array => $item === null ? [] : self::writtenVariables($item->value),
                $target->items,
            ));
        }

        while ($target instanceof ArrayDimFetch || $target instanceof PropertyFetch || $target instanceof MethodCall
            || $target instanceof NullsafePropertyFetch || $target instanceof NullsafeMethodCall
        ) {
            $target = $target->var;
        }

        return $target instanceof Variable && is_string($target->name) ? [$target->name] : [];
    }

    /**
     * Whether a write inside nested functions reaches the body around them: only where each is a closure importing the
     * name by reference, since a by-value import or an arrow function writes a copy. A write through `$this` always
     * reaches, as every function shares the object and none can reassign it.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $enclosing
     */
    private static function reachesBody(string $name, array $enclosing): bool
    {
        if ($name === 'this') {
            return true;
        }

        return array_all($enclosing, fn (ArrowFunction|ClosureExpr $function): bool => $function instanceof ClosureExpr
            && array_any($function->uses, fn (ClosureUse $use): bool => $use->byRef && $use->var->name === $name));
    }

    /**
     * The literal keys a target reaches into its variable through, outermost first: none for the variable itself, and
     * null for a computed or appended key, or anything but a variable at the root.
     *
     * @return list<string>|null
     */
    private static function keyPath(Expr $target): ?array
    {
        $path = [];

        while ($target instanceof ArrayDimFetch) {
            if (! $target->dim instanceof String_ && ! $target->dim instanceof Int_) {
                return null;
            }

            array_unshift($path, (string) $target->dim->value);
            $target = $target->var;
        }

        return $target instanceof Variable && is_string($target->name) ? $path : null;
    }

    /**
     * What array_push(), array_unshift() or array_splice() leaves: the list, with each value it adds, unless one is
     * unpacked. array_splice() adds its replacement, a list literal's items or the one value it is.
     *
     * @param  list<string>  $path
     * @return ByReferenceForm
     */
    private static function appendedForm(CallLike $call, Expr $target, array $path): array
    {
        if ($call instanceof FuncCall && $call->name instanceof Name && $call->name->toLowerString() === 'array_splice') {
            $replacement = CallArguments::fromNames($call->getArgs(), ['array', 'offset', 'length', 'replacement'])
                ->named('replacement')?->value;
            $values = match (true) {
                $replacement === null => [],
                $replacement instanceof Array_ => array_any($replacement->items, fn (?ArrayItem $item): bool => $item === null || $item->unpack)
                    ? null
                    : array_values(array_filter(array_map(fn (?ArrayItem $item): ?Expr => $item?->value, $replacement->items))),
                default => [$replacement],
            };
        } else {
            $added = array_values(array_filter($call->getArgs(), fn (Arg $arg): bool => $arg->value !== $target));
            $values = array_any($added, fn (Arg $arg): bool => $arg->unpack) ? null : array_map(fn (Arg $arg): Expr => $arg->value, $added);
        }

        return $values === null
            ? self::form(ByReferenceEffect::Replaces)
            : self::form(ByReferenceEffect::Appends, $values, $path);
    }

    /**
     * What array_walk() or array_walk_recursive() leaves: every element, when its callback takes none by reference,
     * else each element, or each leaf, with the values its callback writes into it, read only from a closure written
     * at the call and read in that closure's own scope.
     *
     * @param  list<string>  $path
     * @return ByReferenceForm
     */
    private static function walkedForm(CallLike $call, ByReferenceEffect $effect, array $path, AnalysisScope $scope): array
    {
        $callback = CallArguments::fromNames($call->getArgs(), ['array', 'callback', 'arg'])->named('callback')?->value;

        if (! $callback instanceof ClosureExpr && ! $callback instanceof ArrowFunction) {
            return self::form(ByReferenceEffect::Replaces);
        }

        $parameter = $callback->params[0] ?? null;

        if ($parameter === null || ! $parameter->byRef) {
            return self::form(ByReferenceEffect::KeepsAny, [], $path);
        }

        $element = ! $parameter->variadic && $parameter->var instanceof Variable && is_string($parameter->var->name)
            ? $parameter->var->name
            : null;
        $body = $callback instanceof ClosureExpr ? $callback->stmts : [$callback->expr];
        $values = $element === null ? null : self::elementValues(array_values(array_filter(
            self::of($body, $scope),
            fn (array $write): bool => $write[0] === $element,
        )));

        return $values === null ? self::form(ByReferenceEffect::Replaces) : self::form($effect, $values, $path, $callback);
    }

    /**
     * The values writes to an element's variable put in the element, or null when one is a write the scan cannot type:
     * anything but an assignment, an arithmetic or `.=` operator, `??=`, `++` or `--` of the variable itself. An
     * increment stands for its own value, and an operator for the operation it performs.
     *
     * @param  NamedWrites  $writes
     * @return list<Expr>|null
     */
    private static function elementValues(array $writes): ?array
    {
        $values = [];

        foreach ($writes as [, $node, $target]) {
            $value = match (true) {
                ! $target instanceof Variable => null,
                $node instanceof Assign, $node instanceof CoalesceAssign => $node->expr,
                $node instanceof AssignOp => self::compoundValue($node),
                $node instanceof PreInc, $node instanceof PostInc, $node instanceof PreDec, $node instanceof PostDec => $node,
                default => null,
            };

            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * The operation a compound assignment performs, `$v .= 'x'` as `$v . 'x'`, or null for a bitwise or shift operator.
     */
    private static function compoundValue(AssignOp $assign): ?Expr
    {
        $attributes = $assign->getAttributes();

        return match (true) {
            $assign instanceof AssignOp\Concat => new BinaryOp\Concat($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Plus => new BinaryOp\Plus($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Minus => new BinaryOp\Minus($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Mul => new BinaryOp\Mul($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Div => new BinaryOp\Div($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Mod => new BinaryOp\Mod($assign->var, $assign->expr, $attributes),
            $assign instanceof AssignOp\Pow => new BinaryOp\Pow($assign->var, $assign->expr, $attributes),
            default => null,
        };
    }

    /**
     * A by-reference form of an effect, with the values the call adds or writes, the key path it takes, the walk's
     * callback whose scope reads those values, and the span of reads it holds for, every read until it is placed.
     *
     * @param  list<Expr>  $values
     * @param  list<string>  $path
     * @return ByReferenceForm
     */
    private static function form(
        ByReferenceEffect $effect,
        array $values = [],
        array $path = [],
        ArrowFunction|ClosureExpr|null $callback = null,
    ): array {
        return [
            'effect' => $effect,
            'values' => $values,
            'path' => $path,
            'callback' => $callback,
            'after' => PHP_INT_MIN,
            'before' => null,
            'sole' => false,
        ];
    }
}
