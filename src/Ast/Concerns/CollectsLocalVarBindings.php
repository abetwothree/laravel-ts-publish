<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ByReferenceArguments;
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
 * The single `$var = expr;` binding pass. Shared because both the resource analyzer's own
 * method walk and AstEngine::bindingsFor() must read a body's variables the same way.
 *
 * @phpstan-type VariableWrites = list<array{string, Node, Expr}>
 *
 * @internal
 */
trait CollectsLocalVarBindings
{
    /**
     * Record top-level `$var = expr;` statements, and the type an inline `@var` on one declares. The expression binding
     * skips a variable written more than once: this flat list can't tell which write is live at a given return branch,
     * so binding one risks a wrong-but-plausible type instead of unknown.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function collectLocalVarBindings(array $stmts, AnalysisScope $scope): void
    {
        $writes = $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);
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
     */
    protected function endBindingsAtWrites(array $stmts, AnalysisScope $scope): void
    {
        if (! $scope->bindsAnyName()) {
            return;
        }

        // An arrow function captures by value, so no write inside one reaches the body's own variable.
        $arrows = new NodeFinder()->findInstanceOf($stmts, ArrowFunction::class);

        foreach ($this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass) as [$name, $node, $target]) {
            // A write past an end already recorded cannot move it earlier.
            if (! $scope->bindsName($name)
                || (isset($scope->bindingEnds[$name]) && $node->getStartFilePos() > $scope->bindingEnds[$name])
                || array_any($arrows, fn (ArrowFunction $arrow): bool => $this->encloses($arrow, $node))
                || ! $this->replacesVariable($target, $name)
                || $this->keepsBinding($name, $node, $scope)
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
     * Collect every local variable name written anywhere in a statement tree, as collectVariableWrites() counts writes.
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    protected function collectWrittenVariableNames(array $stmts, AnalysisScope $scope): array
    {
        return array_column(
            $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass),
            0,
        );
    }

    /**
     * Every local variable write in a statement tree, as the name written, the node writing it and the expression it
     * writes to: an assignment or increment, a `foreach` target or by-reference array, a `catch` variable, `unset()`,
     * `static`, `global`, a by-reference `use`, or an argument to a by-reference parameter of a callee the call names.
     *
     * Only an assignment, an increment, a `foreach` target and a `use` count every variable they spell; the rest count
     * just the variable they name or key into, so `unset($a[$k])` writes `$a` and `unset($o->p)` writes nothing.
     *
     * A nested closure or arrow function's write reaches the body only for a name it does not own; a nested class's or
     * named function's never does.
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
     */
    private function keepsBinding(string $name, Node $node, AnalysisScope $scope): bool
    {
        if (($node instanceof AssignOp && ! $node instanceof CoalesceAssign)
            || $node instanceof PreInc || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec
        ) {
            return true;
        }

        // A `foreach` target, `catch`, `unset()`, destructuring or by-reference write holds a value this does not read.
        if ((! $node instanceof Assign && ! $node instanceof CoalesceAssign) || ! $node->var instanceof Variable) {
            return false;
        }

        $resolver = resolve(ReceiverClassResolver::class);
        $span = $node instanceof Assign
            ? array_find($scope->varDocBindings[$name] ?? [], fn (array $binding): bool => $binding['expr'] === $node->expr)
            : null;
        $held = $span === null ? $resolver->resolve($node->expr, $scope) : $resolver->resolveDeclared($name, $span, $scope);
        $element = $scope->varCollectionBindings[$name]['modelFqcn'] ?? null;
        $classes = match (true) {
            isset($scope->varModelBindings[$name]) => [$scope->varModelBindings[$name]],
            isset($scope->varClassBindings[$name]) => $scope->varClassBindings[$name],
            isset($scope->requestVarNames[$name]) => [$scope->requestVarNames[$name]],
            default => null,
        };
        $declared = $scope->varDeclaredBindings[$name] ?? null;

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
     * Whether a write inside nested functions reaches the body around them: a parameter of any of them owns its name,
     * and so does a `function () use (…)` closure for every name other than `$this` it does not import.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $enclosing
     */
    private function reachesBody(string $name, array $enclosing): bool
    {
        foreach ($enclosing as $function) {
            foreach ($function->params as $param) {
                if ($param->var instanceof Variable && $param->var->name === $name) {
                    return false;
                }
            }

            if ($function instanceof ClosureExpr && $name !== 'this'
                && ! array_any($function->uses, fn (ClosureUse $use): bool => $use->var->name === $name)
            ) {
                return false;
            }
        }

        return true;
    }
}
