<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use Illuminate\Support\Enumerable;
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
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The one sequence that seeds a method's or a closure's body, seedBody(), and the write scan its passes share. Every
 * reader of a body seeds it here, so each reads the body's variables the same way.
 *
 * @phpstan-type VariableWrites = list<array{string, Node, Expr}>
 *
 * @phpstan-import-type ByReferenceForm from AnalysisScope
 *
 * @internal
 */
trait CollectsLocalVarBindings
{
    use CollectsInstanceofGuards;

    /**
     * Seed a body once its parameters are bound: its own locals, the end of each binding it came in with at the write
     * that replaces it, then its guards, all from one scan of its writes.
     *
     * A local shadows an outer binding of its name however often the body writes it; a method body starts with none.
     * A by-reference call that keeps a value in some form writes none: its form is recorded for the read to check.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function seedBody(array $stmts, AnalysisScope $scope): void
    {
        $writes = $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);
        [$replacing, $kept] = $this->partitionWrites($writes, $scope);

        foreach ($replacing as [$name]) {
            unset($scope->localVarBindings[$name], $scope->varDocBindings[$name], $scope->byReferenceForms[$name]);
        }

        foreach ($kept as [$name, $form]) {
            if ($form['effect'] !== ByReferenceEffect::KeepsAny) {
                $scope->byReferenceForms[$name][] = $form;
            }
        }

        $this->collectLocalVarBindings($stmts, $scope, $replacing);
        // After the locals: keepsBinding() reads the `@var` spans they record.
        $this->endBindingsAtWrites($stmts, $scope, $writes);
        // A call that keeps a value leaves it an array or a number, so it ends no guard's class or non-null proof.
        $this->collectInstanceofGuards($stmts, $scope, $replacing);
    }

    /**
     * Record top-level `$var = expr;` statements, and the type an inline `@var` on one declares. The expression binding
     * skips a variable written more than once: this flat list can't tell which write is live at a given return branch,
     * so binding one risks a wrong-but-plausible type instead of unknown.
     *
     * A by-reference call that keeps the value in some form is no write here; seedBody() records the form it keeps.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites|null  $writes  the body's writes that replace a variable, when the caller has sorted them
     */
    protected function collectLocalVarBindings(array $stmts, AnalysisScope $scope, ?array $writes = null): void
    {
        $writes ??= $this->partitionWrites(
            $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass),
            $scope,
        )[0];
        $writeCounts = array_count_values(array_column($writes, 0));

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof ExpressionStmt
                || ! $stmt->expr instanceof Assign
                || ! $stmt->expr->var instanceof Variable
                || ! is_string($stmt->expr->var->name)
            ) {
                continue;
            }

            $name = $stmt->expr->var->name;

            if (($writeCounts[$name] ?? 0) === 1) {
                $scope->localVarBindings[$name] = $stmt->expr->expr;
            }

            $this->bindDeclaredType($stmt, $stmt->expr, $name, $stmts, $writes, $scope);
        }
    }

    /**
     * End each binding a name came into the body with, a parameter's or a seeded one, at the body's first write that
     * replaces the variable with a value naming a class the binding does not hold; a later read takes what the body
     * wrote. A value naming no class leaves the binding standing, since it is all that is known.
     *
     * A top-level `$x = …;` ends it past its own statement, so the reads its value makes keep the old binding; a write
     * nested in a statement ends it where that statement starts, since a loop can run the write before a read.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites|null  $writes  the body's writes, when the caller has scanned them
     */
    protected function endBindingsAtWrites(array $stmts, AnalysisScope $scope, ?array $writes = null): void
    {
        if (! $scope->bindsAnyName()) {
            return;
        }

        $writes ??= $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);

        foreach ($writes as [$name, $node, $target]) {
            // A write past an end already recorded cannot move it earlier.
            if (! $scope->bindsName($name)
                || (isset($scope->bindingEnds[$name]) && $node->getStartFilePos() > $scope->bindingEnds[$name])
                || ! $this->replacesVariable($target, $name)
                || $this->keepsBinding($name, $node, $this->byReferenceForm($node, $target, $writes, $scope), $scope)
            ) {
                continue;
            }

            foreach ($stmts as $stmt) {
                if ($this->encloses($stmt, $node)) {
                    $end = $stmt instanceof ExpressionStmt && $stmt->expr === $node && ($node instanceof Assign || $node instanceof AssignOp)
                        ? $stmt->getEndFilePos()
                        : $stmt->getStartFilePos() - 1;
                    $scope->bindingEnds[$name] = min($scope->bindingEnds[$name] ?? $end, $end);

                    break;
                }
            }
        }
    }

    /**
     * Collect every local variable name written anywhere in a statement tree, as collectVariableWrites() counts writes,
     * but a by-reference call that keeps the value in some form, whose form the read checks.
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    protected function collectWrittenVariableNames(array $stmts, AnalysisScope $scope): array
    {
        return array_column($this->partitionWrites(
            $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass),
            $scope,
        )[0], 0);
    }

    /**
     * Every local variable write in a statement tree, as the name written, the node writing it and the expression it
     * writes to: an assignment or increment, a `foreach` target or by-reference array, a `catch` variable, `unset()`,
     * `static`, `global`, a by-reference `use`, or an argument to a by-reference parameter of a callee the call names.
     *
     * Only an assignment, an increment, a `foreach` target and a `use` count every variable they spell; the rest count
     * just the variable they name or key into, so `unset($a[$k])` writes `$a` and `unset($o->p)` writes nothing.
     *
     * A nested closure's write reaches the body only for a name it imports by reference; an arrow function's, a nested
     * class's or a named function's never does.
     *
     * @param  array<Node>  $stmts
     * @param  ReflectionClass<object>|null  $subject  the class `$this`, `self` and `static` name, for a call's callee
     * @param  ReflectionClass<object>|null  $declaration  the class or trait whose body holds the statements, else the
     *                                                     subject
     * @return VariableWrites
     */
    protected function collectVariableWrites(
        array $stmts,
        ?ReflectionClass $subject = null,
        ?ReflectionClass $declaration = null,
    ): array {
        /** @var VariableWrites $writes */
        $writes = [];

        $this->collectWritesInto($stmts, [], $writes, $subject, $declaration ?? $subject);

        return $writes;
    }

    /**
     * The writes to a variable that can still land once the body reaches an offset: each that does not end before it.
     *
     * The one position-ordered rule both the early-exit guard pass and an inline `@var` read a variable's writes by.
     *
     * @param  VariableWrites  $writes
     * @return list<Node>
     */
    protected function writesFrom(string $name, int $offset, array $writes): array
    {
        $from = [];

        foreach ($writes as [$written, $node]) {
            if ($written === $name && $node->getEndFilePos() >= $offset) {
                $from[] = $node;
            }
        }

        return $from;
    }

    /**
     * Bind a variable to the type an inline `@var` on its assignment declares, for the reads after that statement and
     * before the top-level statement holding its next write. A top-level statement runs once, so an earlier one never
     * sees a later write; a loop holding both is one statement, so the span ends where it starts, not at the write.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function bindDeclaredType(
        ExpressionStmt $assignment,
        Assign $assign,
        string $name,
        array $stmts,
        array $writes,
        AnalysisScope $scope,
    ): void {
        $tag = resolve(PropertyDocblockTypeReader::class)
            ->extractVarTag((string) $assignment->getDocComment()?->getText(), $scope->declaringFileClass);

        if ($tag === null || ($tag[1] !== null && $tag[1] !== $name)) {
            return;
        }

        $before = null;

        foreach ($this->writesFrom($name, $assignment->getStartFilePos(), $writes) as $write) {
            if ($write === $assign) {
                continue;
            }

            foreach ($stmts as $stmt) {
                if ($stmt->getStartFilePos() <= $write->getStartFilePos() && $write->getEndFilePos() <= $stmt->getEndFilePos()) {
                    $before = min($before ?? $stmt->getStartFilePos(), $stmt->getStartFilePos());

                    break;
                }
            }
        }

        // A second write in the assigning statement itself may be the one its reads see.
        if ($before !== null && $before <= $assignment->getStartFilePos()) {
            return;
        }

        $scope->varDocBindings[$name][] = [
            'type' => $tag[0],
            'context' => $scope->declaringFileClass,
            'after' => $assignment->getEndFilePos(),
            'before' => $before,
            'expr' => $assign->expr,
        ];
    }

    /**
     * Whether a write leaves a binding standing. `.=` or `++` keeps the kind of value held; an assignment or `??=`
     * keeps it unless its value names a class the binding does not hold, other or fewer classes than a declared one,
     * or a collection of another element. An inline `@var` on an assignment answers where its value names none.
     *
     * @param  ByReferenceForm|null  $form  what a by-reference call or `foreach` leaves, when one makes the write
     */
    private function keepsBinding(string $name, Node $node, ?array $form, AnalysisScope $scope): bool
    {
        if (($node instanceof AssignOp && ! $node instanceof CoalesceAssign)
            || $node instanceof PreInc || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec
        ) {
            return true;
        }

        $element = $scope->varCollectionBindings[$name]['modelFqcn'] ?? null;
        $classes = match (true) {
            isset($scope->varModelBindings[$name]) => [$scope->varModelBindings[$name]],
            isset($scope->varClassBindings[$name]) => $scope->varClassBindings[$name],
            isset($scope->requestVarNames[$name]) => [$scope->requestVarNames[$name]],
            default => null,
        };
        $declared = $scope->varDeclaredBindings[$name] ?? null;

        // A by-reference call keeps only an array or a number, never the object a class binding holds.
        if ($form !== null) {
            return $form['effect'] === ByReferenceEffect::KeepsAny
                || ($form['effect'] !== ByReferenceEffect::Replaces && $classes === null && $declared === null && $element === null);
        }

        // A `foreach` target, `catch`, `unset()` or destructuring write holds a value this does not read.
        if ((! $node instanceof Assign && ! $node instanceof CoalesceAssign) || ! $node->var instanceof Variable) {
            return false;
        }

        $resolver = resolve(ReceiverClassResolver::class);
        $span = $node instanceof Assign
            ? array_find($scope->varDocBindings[$name] ?? [], fn (array $binding): bool => $binding['expr'] === $node->expr)
            : null;
        $held = $span === null ? $resolver->resolve($node->expr, $scope) : $resolver->resolveDeclared($name, $span, $scope);

        return match (true) {
            $held === null => true,
            $classes !== null => $held->within($classes),
            // A write holding fewer of a declared union's classes ends it, so a later read takes the narrower value.
            $declared !== null => $held->within($declared) && array_all(
                $declared,
                fn (string $class): bool => array_any($held->classes, fn (string $heldClass): bool => is_a($class, $heldClass, true)),
            ),
            // A re-sorted or re-filtered collection names no element class, so the element binding stands.
            $element !== null => $held->within([Enumerable::class])
                && ($held->elementModel === null || is_a($held->elementModel, $element, true)),
            default => false,
        };
    }

    /**
     * Split a body's writes into those that replace a variable's value and the by-reference ones that keep it in some
     * form, each with the form it keeps.
     *
     * @param  VariableWrites  $writes
     * @return array{VariableWrites, list<array{string, ByReferenceForm}>}
     */
    private function partitionWrites(array $writes, AnalysisScope $scope): array
    {
        $replacing = [];
        $kept = [];

        foreach ($writes as $write) {
            $form = $this->byReferenceForm($write[1], $write[2], $writes, $scope);

            if ($form === null || $form['effect'] === ByReferenceEffect::Replaces) {
                $replacing[] = $write;
            } else {
                $kept[] = [$write[0], $form];
            }
        }

        return [$replacing, $kept];
    }

    /**
     * What a by-reference argument or a by-reference `foreach` leaves in the variable it writes, with the values it
     * adds or writes into an element; null for a write neither makes. A key of a variable is always replaced.
     *
     * @param  VariableWrites  $writes  every write of the body, for those a `foreach` element's variable makes
     * @return ByReferenceForm|null
     */
    private function byReferenceForm(Node $node, Expr $target, array $writes, AnalysisScope $scope): ?array
    {
        if ($node instanceof Foreach_ && $node->byRef && $node->expr === $target) {
            $element = $node->valueVar instanceof Variable && is_string($node->valueVar->name) ? $node->valueVar->name : null;
            // After the loop the variable still points at the last element, so every write to it counts, but unset().
            $values = $element === null || ! $target instanceof Variable ? null : $this->elementValues(array_values(array_filter(
                $writes,
                fn (array $write): bool => $write[0] === $element && $write[1] !== $node && ! $write[1] instanceof Unset_,
            )));

            return $this->form($values === null ? ByReferenceEffect::Replaces : ByReferenceEffect::WritesElements, $values ?? []);
        }

        if (! $node instanceof CallLike) {
            return null;
        }

        $effect = $target instanceof Variable
            ? resolve(ByReferenceArguments::class)->effect($node, $target)
            : ByReferenceEffect::Replaces;

        return match ($effect) {
            ByReferenceEffect::Appends => $this->appendedForm($node, $target),
            ByReferenceEffect::WritesElements => $this->walkedForm($node, $scope),
            default => $this->form($effect),
        };
    }

    /**
     * What array_push() or array_unshift() leaves: the list, with each value it adds, unless one is unpacked.
     *
     * @return ByReferenceForm
     */
    private function appendedForm(CallLike $call, Expr $target): array
    {
        $added = array_values(array_filter($call->getArgs(), fn (Arg $arg): bool => $arg->value !== $target));

        return array_any($added, fn (Arg $arg): bool => $arg->unpack)
            ? $this->form(ByReferenceEffect::Replaces)
            : $this->form(ByReferenceEffect::Appends, array_map(fn (Arg $arg): Expr => $arg->value, $added));
    }

    /**
     * What array_walk() or array_walk_recursive() leaves: every element, when its callback takes none by reference,
     * else each element with the values its callback writes into it, read only from a closure written at the call.
     *
     * @return ByReferenceForm
     */
    private function walkedForm(CallLike $call, AnalysisScope $scope): array
    {
        $callback = CallArguments::fromNames($call->getArgs(), ['array', 'callback', 'arg'])->named('callback')?->value;

        if (! $callback instanceof ClosureExpr && ! $callback instanceof ArrowFunction) {
            return $this->form(ByReferenceEffect::Replaces);
        }

        $parameter = $callback->params[0] ?? null;

        if ($parameter === null || ! $parameter->byRef) {
            return $this->form(ByReferenceEffect::KeepsAny);
        }

        $element = ! $parameter->variadic && $parameter->var instanceof Variable && is_string($parameter->var->name)
            ? $parameter->var->name
            : null;
        $body = $callback instanceof ClosureExpr ? $callback->stmts : [$callback->expr];
        $values = $element === null ? null : $this->elementValues(array_values(array_filter(
            $this->collectVariableWrites($body, $scope->subjectReflection, $scope->declaringFileClass),
            fn (array $write): bool => $write[0] === $element,
        )));

        return $values === null
            ? $this->form(ByReferenceEffect::Replaces)
            : $this->form(ByReferenceEffect::WritesElements, $values);
    }

    /**
     * The values writes to an element's variable put in the element, or null when one is a write the scan cannot type:
     * anything but an assignment, `??=`, `++` or `--` of the variable itself. An increment stands for its own value.
     *
     * @param  VariableWrites  $writes
     * @return list<Expr>|null
     */
    private function elementValues(array $writes): ?array
    {
        $values = [];

        foreach ($writes as [, $node, $target]) {
            $value = match (true) {
                ! $target instanceof Variable => null,
                $node instanceof Assign, $node instanceof CoalesceAssign => $node->expr,
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
     * A by-reference form of an effect, with the values the call adds or writes.
     *
     * @param  list<Expr>  $values
     * @return ByReferenceForm
     */
    private function form(ByReferenceEffect $effect, array $values = []): array
    {
        return ['effect' => $effect, 'values' => $values];
    }

    /**
     * Whether a node lies within another by their file offsets.
     */
    private function encloses(Node $outer, Node $inner): bool
    {
        return $outer->getStartFilePos() <= $inner->getStartFilePos() && $inner->getEndFilePos() <= $outer->getEndFilePos();
    }

    /**
     * Whether a write target replaces the variable's whole value: the variable itself, or an item of a destructuring
     * list. A write through `$x->prop` or `$x['key']` leaves `$x` holding the same value.
     */
    private function replacesVariable(Expr $target, string $name): bool
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            return array_any(
                $target->items,
                fn (?ArrayItem $item): bool => $item !== null && $this->replacesVariable($item->value, $name),
            );
        }

        return $target instanceof Variable && $target->name === $name;
    }

    /**
     * Walk nodes for the writes that reach the body, tracking the nested closures and arrow functions around each.
     *
     * @param  array<mixed>|Node  $nodes
     * @param  list<ArrowFunction|ClosureExpr>  $enclosing  the nested functions between the body and the nodes
     * @param  VariableWrites  $writes
     * @param  ReflectionClass<object>|null  $subject
     * @param  ReflectionClass<object>|null  $declaration
     */
    private function collectWritesInto(
        array|Node $nodes,
        array $enclosing,
        array &$writes,
        ?ReflectionClass $subject,
        ?ReflectionClass $declaration,
    ): void {
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if (is_array($node)) {
                $this->collectWritesInto($node, $enclosing, $writes, $subject, $declaration);

                continue;
            }

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_) {
                continue;
            }

            foreach ($this->writeTargets($node) as $target) {
                foreach ($this->writtenVariables($target) as $name) {
                    if ($this->reachesBody($name, $enclosing)) {
                        $writes[] = [$name, $node, $target];
                    }
                }
            }

            foreach ($this->rootedWriteTargets($node, $subject, $declaration) as $target) {
                $name = $this->rootVariable($target);

                if ($name !== null && $this->reachesBody($name, $enclosing)) {
                    $writes[] = [$name, $node, $target];
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
                    $this->collectWritesInto($child, $within, $writes, $subject, $declaration);
                }
            }
        }
    }

    /**
     * The expressions an assignment, an increment, a `foreach` target or a by-reference `use` writes to, each variable
     * they spell counted as written.
     *
     * @return list<Expr>
     */
    private function writeTargets(Node $node): array
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
     * @param  ReflectionClass<object>|null  $subject
     * @param  ReflectionClass<object>|null  $declaration
     * @return list<Expr>
     */
    private function rootedWriteTargets(Node $node, ?ReflectionClass $subject, ?ReflectionClass $declaration): array
    {
        if ($node instanceof CallLike) {
            // Only a call passing a variable or a key of one pays for reflecting its callee.
            $passed = $node->isFirstClassCallable() ? [] : array_filter(
                $node->getArgs(),
                fn (Arg $arg): bool => $this->rootVariable($arg->value) !== null,
            );

            return $passed === []
                ? []
                : resolve(ByReferenceArguments::class)->of($node, $passed, $subject, $declaration);
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
     * The variable a target rewrites when it is that variable or a key of it; a property keeps its holder, and a
     * temporary such as a call's result holds no variable.
     */
    private function rootVariable(Expr $target): ?string
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        return $target instanceof Variable && is_string($target->name) ? $target->name : null;
    }

    /**
     * The names of the variables a write target spells.
     *
     * @return list<string>
     */
    private function writtenVariables(Expr $target): array
    {
        if ($target instanceof Variable && is_string($target->name)) {
            return [$target->name];
        }

        $names = [];

        foreach (new NodeFinder()->find($target, fn (Node $node): bool => $node instanceof Variable) as $variable) {
            if ($variable instanceof Variable && is_string($variable->name)) {
                $names[] = $variable->name;
            }
        }

        return $names;
    }

    /**
     * Whether a write inside nested functions reaches the body around them: only where each is a closure importing the
     * name by reference, since a by-value import or an arrow function writes a copy. A write through `$this` always
     * reaches, as every function shares the object and none can reassign it.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $enclosing
     */
    private function reachesBody(string $name, array $enclosing): bool
    {
        if ($name === 'this') {
            return true;
        }

        return array_all($enclosing, fn (ArrowFunction|ClosureExpr $function): bool => $function instanceof ClosureExpr
            && array_any($function->uses, fn (ClosureUse $use): bool => $use->byRef && $use->var->name === $name));
    }
}
