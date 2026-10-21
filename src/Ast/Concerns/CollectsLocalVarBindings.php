<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrite;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
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
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The one sequence that seeds a method's or a closure's body, seedBody(), and the write scan its passes share. Every
 * reader of a body seeds it here, so each reads the body's variables the same way.
 *
 * @phpstan-type NamedWrite = array{string, Node, Expr}
 * @phpstan-type VariableWrites = list<NamedWrite>
 * @phpstan-type KeptWrites = list<array{string, ByReferenceForm}>
 * @phpstan-type PartitionedWrites = array{VariableWrites, KeptWrites}
 * @phpstan-type JoinedState = array{list<Expr>|null, bool, bool, list<Expr>|null}
 *
 * @phpstan-import-type ByReferenceForm from AnalysisScope
 * @phpstan-import-type BindingGap from AnalysisScope
 *
 * @internal
 */
trait CollectsLocalVarBindings
{
    use CollectsInstanceofGuards;

    /**
     * Seed a body once its parameters are bound: its own locals, top-level and block-scoped, the end of each binding it
     * came in with at the write that replaces it, then its guards, all from one scan of its writes.
     *
     * A local shadows an outer binding of its name however often the body writes it; a method body starts with none.
     * A by-reference call that keeps a value in some form writes none: its form is recorded for the read to check.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function seedBody(array $stmts, AnalysisScope $scope): void
    {
        $writes = $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);
        [$replacing, $kept] = $this->partitionWrites($writes, $stmts, $scope);

        foreach ($replacing as [$name]) {
            unset($scope->localVarBindings[$name], $scope->varDocBindings[$name], $scope->byReferenceForms[$name]);

            // An outer block local's span may cover this body, as a closure in that block. A bound name's gap holds the
            // value its block wrote, which a closure capturing it still reads before its own write.
            if (! $scope->bindsName($name)) {
                unset($scope->bindingGaps[$name]);
            }
        }

        // A name no write but preg_match() reaches holds the matches past a call that surely replaced what it held.
        $written = array_column([...$replacing, ...array_filter(
            $kept,
            fn (array $write): bool => $write[1]['effect'] !== ByReferenceEffect::WritesMatches,
        )], 0);

        foreach ($kept as [$name, $form]) {
            if ($form['effect'] !== ByReferenceEffect::KeepsAny) {
                $scope->byReferenceForms[$name][] = [...$form, 'sole' => ! in_array($name, $written, true)];
            }
        }

        $this->collectLocalVarBindings($stmts, $scope, $replacing);
        $this->bindBlockLocals($stmts, $scope, $replacing);
        // After the locals: keepsBinding() reads the `@var` spans they record.
        $ended = $this->endBindingsAtWrites($stmts, $scope, $writes);
        $this->bindJoinedLocals($stmts, $scope, $replacing, $ended);
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
            $stmts,
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
            ReceiverClassResolver::markFilled($stmt->expr->expr, $name, $stmts);

            if (($writeCounts[$name] ?? 0) === 1) {
                $scope->localVarBindings[$name] = $stmt->expr->expr;
            }

            $this->bindDeclaredType($stmt, $stmt->expr, $name, $stmts, $writes, $scope);
        }
    }

    /**
     * Bind each `$x = …;` a nested block holds, and the type an inline `@var` on it declares, for the reads past it up
     * to its block's end or the start of that block's statement holding the next write: a block may not run, and a loop
     * runs it again. A name holding a parameter's or a seeded binding keeps the rules endBindingsAtWrites() sets.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     */
    protected function bindBlockLocals(array $stmts, AnalysisScope $scope, array $writes): void
    {
        foreach ($stmts as $stmt) {
            foreach ($this->innerBlocks($stmt) as $block) {
                $this->bindBlock($block, $stmts, $scope, $writes);
            }
        }
    }

    /**
     * Bind each local the body writes more than once for the reads between its writes: past a top-level `$x = …;` the
     * value it assigns, past a statement whose writes are all nested `$x = …` the values before it and theirs, and past
     * a `try` what its body, a catch and its finally leave. Any other write leaves it unbound until such a write.
     *
     * A name a reference can write through, made by `=&`, `use (&$x)`, `global`, `static` or `foreach (… as &$x)`, is
     * never joined: another name or a closure may write it at any point, as blockSpanEnd() also declines.
     *
     * A parameter, or a seeded name, starts holding its binding, which nested writes join for a value read only. While
     * it holds, a write of another kind, or its own `$x = …;` where it binds no value, stops the walk, leaving the rest
     * to endBindingsAtWrites(); else that `$x = …;` replaces the value. Past an ending write it walks as a local.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     * @param  array<string, Node>  $ended  the write that ended each binding, from endBindingsAtWrites()
     */
    protected function bindJoinedLocals(array $stmts, AnalysisScope $scope, array $writes, array $ended = []): void
    {
        $byName = [];

        foreach ($writes as $write) {
            $byName[$write[0]][] = $write;
        }

        foreach ($byName as $name => $own) {
            $bound = $scope->bindsName((string) $name);

            if (array_any($own, fn (array $write): bool => $this->writeKind($write[1], $write[2])->makesReference())) {
                continue;
            }

            if ($bound || count($own) > 1) {
                $initial = $bound ? [[], true, true, []] : [null, false, false, null];
                $this->joinStatements((string) $name, $stmts, $stmts, $own, $ended[$name] ?? null, $initial, null, $scope);
            }
        }
    }

    /**
     * End each binding a name came into the body with, a parameter's or a seeded one, at the body's first write that
     * replaces the variable with a value naming a class the binding does not hold, or a value binding's own top-level
     * `$x = …;`; a later read takes what the body wrote. Else a value naming no class leaves it, as all that is known.
     *
     * A top-level `$x = …;` ends it past its own statement, so the reads its value makes keep the old binding; a write
     * nested in a statement ends it where that statement starts, since a loop can run the write before a read. A write
     * in an `if` or `switch` block that always leaves the function (exitingBlock()) ends it inside that block alone.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites|null  $writes  the body's writes, when the caller has scanned them
     * @return array<string, Node> the write that ended each binding a write ended
     */
    protected function endBindingsAtWrites(array $stmts, AnalysisScope $scope, ?array $writes = null): array
    {
        if (! $scope->bindsAnyName()) {
            return [];
        }

        $ended = [];
        $writes ??= $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);

        foreach ($writes as [$name, $node, $target]) {
            // A write past an end already recorded cannot move it earlier.
            if (! $scope->bindsName($name)
                || (isset($scope->bindingEnds[$name]) && $node->getStartFilePos() > $scope->bindingEnds[$name])
                || ! $this->replacesVariable($target, $name)
            ) {
                continue;
            }

            $kind = $this->writeKind($node, $target);
            $form = $this->byReferenceForm($node, $target, $writes, $stmts, $scope);

            if ($this->keepsBinding($name, $node, $kind, $form, $scope, $this->isStatementOf($stmts, $node))) {
                continue;
            }

            $block = $this->exitingBlock($stmts, $node);
            $end = $this->endWithin($block ?? $stmts, $node);

            if ($end === null) {
                continue;
            }

            if ($block === null) {
                if ($end < ($scope->bindingEnds[$name] ?? PHP_INT_MAX)) {
                    $ended[$name] = $node;
                }

                $scope->bindingEnds[$name] = min($scope->bindingEnds[$name] ?? $end, $end);

                continue;
            }

            // A write nested in a block that falls through, or a `??=`, may leave the old binding standing past it.
            $nested = ! $this->isStatementOf($block, $node);
            $assigned = ($kind === VariableWrite::Replaces || $kind === VariableWrite::Coalesces)
                && ($node instanceof Assign || $node instanceof CoalesceAssign);
            $this->addGap($scope, $name, [
                $end,
                $block[array_key_last($block)]->getEndFilePos(),
                $assigned ? [$node->expr] : null,
                $nested || $kind === VariableWrite::Coalesces,
                false,
            ]);
        }

        return $ended;
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
            $stmts,
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
     * A nested closure's write reaches the body only for a name it imports by reference, and an arrow function's only
     * through `$this`; a nested class's or a named function's never does.
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
        if ($assignment->getDocComment() === null) {
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

        $this->bindDeclaredSpan($assignment, $assign, $name, $assignment->getEndFilePos(), $before, $scope);
    }

    /**
     * Bind a variable to the type an inline `@var` on its assignment declares, for the reads past $after and, when it
     * is set, before $before.
     */
    private function bindDeclaredSpan(
        ExpressionStmt $assignment,
        Assign $assign,
        string $name,
        int $after,
        ?int $before,
        AnalysisScope $scope,
    ): void {
        if ($assignment->getDocComment() === null) {
            return;
        }

        $tag = resolve(PropertyDocblockTypeReader::class)
            ->extractVarTag($assignment->getDocComment()->getText(), $scope->declaringFileClass);

        if ($tag === null || ($tag[1] !== null && $tag[1] !== $name)) {
            return;
        }

        $scope->varDocBindings[$name][] = [
            'type' => $tag[0],
            'context' => $scope->declaringFileClass,
            'after' => $after,
            'before' => $before,
            'expr' => $assign->expr,
        ];
    }

    /**
     * Bind the assignments of one nested block, then those of the blocks its statements hold.
     *
     * @param  array<Node\Stmt>  $block
     * @param  array<Node\Stmt>  $body  the whole body, for the writes that fill a `new` model a local holds
     * @param  VariableWrites  $writes
     */
    private function bindBlock(array $block, array $body, AnalysisScope $scope, array $writes): void
    {
        foreach ($block as $stmt) {
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && is_string($stmt->expr->var->name)
            ) {
                ReceiverClassResolver::markFilled($stmt->expr->expr, $stmt->expr->var->name, $body);
                $this->bindBlockAssignment($stmt, $stmt->expr, $stmt->expr->var->name, $block, $scope, $writes);
            }

            foreach ($this->innerBlocks($stmt) as $inner) {
                $this->bindBlock($inner, $body, $scope, $writes);
            }
        }
    }

    /**
     * Bind one nested `$x = …;` for the reads past its statement, up to the end of its block or the start of the
     * block's statement holding the name's next write.
     *
     * @param  non-empty-array<Node\Stmt>  $block
     * @param  VariableWrites  $writes
     */
    private function bindBlockAssignment(
        ExpressionStmt $statement,
        Assign $assign,
        string $name,
        array $block,
        AnalysisScope $scope,
        array $writes,
    ): void {
        $after = $statement->getEndFilePos();
        $end = $this->blockSpanEnd($name, $after, $block, $writes);

        if ($end === null || $end <= $after) {
            return;
        }

        $this->bindDeclaredSpan($statement, $assign, $name, $after, $end + 1, $scope);

        if (! $scope->bindsName($name)) {
            $scope->bindingGaps[$name][] = [$after, $end, [$assign->expr], false, false];
        }
    }

    /**
     * Where the span of a block's write past $after ends: before the block's statement holding the name's next write,
     * else at the block's end. Null where another name or a closure can write the variable through a reference.
     *
     * @param  non-empty-array<Node\Stmt>  $block
     * @param  VariableWrites  $writes
     */
    private function blockSpanEnd(string $name, int $after, array $block, array $writes): ?int
    {
        $end = $block[array_key_last($block)]->getEndFilePos();

        foreach ($writes as [$written, $node, $target]) {
            if ($written !== $name) {
                continue;
            }

            if ($this->writeKind($node, $target)->makesReference()) {
                return null;
            }

            $holder = $node->getStartFilePos() > $after
                ? array_find($block, fn (Node\Stmt $later): bool => $this->encloses($later, $node))
                : null;
            $end = $holder === null ? $end : min($end, $holder->getStartFilePos() - 1);
        }

        return $end;
    }

    /**
     * Whether a write leaves a binding standing. `.=` or `++` keeps the kind of value held; an assignment or `??=`
     * keeps it unless its value names a class the binding does not hold, other or fewer classes than a declared one,
     * or values other than a collection binding's models. An inline `@var` answers where its value names none.
     *
     * @param  ByReferenceForm|null  $form  what a by-reference call or `foreach` leaves, when one makes the write
     * @param  bool  $topLevel  the write is a statement of the body itself, not of a block
     */
    private function keepsBinding(
        string $name,
        Node $node,
        VariableWrite $kind,
        ?array $form,
        AnalysisScope $scope,
        bool $topLevel,
    ): bool {
        if ($kind === VariableWrite::Compounds) {
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

        // preg_match() may not run unless it is a statement of the body, and a skipped call leaves the binding.
        if ($form !== null && $form['effect'] === ByReferenceEffect::WritesMatches) {
            return ! $topLevel;
        }

        // A by-reference call keeps only an array or a number, never the object a class binding holds.
        if ($form !== null) {
            return $form['effect'] === ByReferenceEffect::KeepsAny
                || ($form['effect'] !== ByReferenceEffect::Replaces && $classes === null && $declared === null && $element === null);
        }

        // A `foreach` target, `catch`, `unset()` or destructuring write holds a value this does not read.
        if (($kind !== VariableWrite::Replaces && $kind !== VariableWrite::Coalesces)
            || (! $node instanceof Assign && ! $node instanceof CoalesceAssign)
        ) {
            return false;
        }

        // A value binding holds no class a receiver keeps, so its own `$x = …;` replaces it past that statement.
        if ($topLevel && $kind === VariableWrite::Replaces && $this->bindsValueOnly($name, $scope)) {
            return false;
        }

        $resolver = resolve(ReceiverClassResolver::class);
        $span = $node instanceof Assign
            ? array_find($scope->varDocBindings[$name] ?? [], fn (array $binding): bool => $binding['expr'] === $node->expr)
            : null;
        $held = $span === null ? $resolver->resolve($node->expr, $scope) : $resolver->resolveDeclared($name, $span, $scope);

        return match (true) {
            // A step such as pluck() or groupBy() replaces a collection's models, though the resolver may name nothing.
            $element !== null && $resolver->replacesItems($node->expr) => false,
            $held === null => true,
            $classes !== null => $held->within($classes),
            // A write holding fewer of a declared union's classes ends it, so a later read takes the narrower value.
            $declared !== null => $held->within($declared) && array_all(
                $declared,
                fn (string $class): bool => array_any($held->classes, fn (string $heldClass): bool => is_a($class, $heldClass, true)),
            ),
            // A collection naming no element, as `collect()` or `merge()` hands back, still holds the models.
            $element !== null => $held->within([Enumerable::class])
                && ($held->elementModel === null || is_a($held->elementModel, $element, true)),
            default => false,
        };
    }

    /**
     * Whether a node is the expression of one of the statements, `$x = …;` itself rather than a write nested in one.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function isStatementOf(array $stmts, Node $node): bool
    {
        return array_any($stmts, fn (Node\Stmt $stmt): bool => $stmt instanceof ExpressionStmt && $stmt->expr === $node);
    }

    /**
     * Split a body's writes into those that replace a variable's value and the by-reference ones that keep it in some
     * form, each with the form it keeps.
     *
     * @param  VariableWrites  $writes
     * @param  array<Node>  $stmts  the body the writes were scanned from
     * @return PartitionedWrites
     */
    private function partitionWrites(array $writes, array $stmts, AnalysisScope $scope): array
    {
        $replacing = [];
        $kept = [];

        foreach ($writes as $write) {
            $form = $this->byReferenceForm($write[1], $write[2], $writes, $stmts, $scope);

            if ($form === null || $form['effect'] === ByReferenceEffect::Replaces) {
                $replacing[] = $write;

                continue;
            }

            // A read before the call, such as a copy's, sees the value it held; a call in a block that returns
            // leaves the reads past that block alone, as a binding end or gap does.
            $block = $this->exitingBlock($stmts, $write[1]);
            $form['after'] = $this->endWithin($block ?? $stmts, $write[1]) ?? PHP_INT_MIN;
            $form['before'] = $block === null ? null : $block[array_key_last($block)]->getEndFilePos();
            $kept[] = [$write[0], $form];
        }

        return [$replacing, $kept];
    }

    /**
     * What a by-reference argument or a by-reference `foreach` leaves in the variable it writes, or in the member a key
     * path of literal keys names, with the values it adds or writes into an element; null for a write neither makes.
     *
     * @param  VariableWrites  $writes  every write of the body, for those a `foreach` element's variable makes
     * @param  array<Node>  $stmts  the body, for the loops around a `foreach`
     * @return ByReferenceForm|null
     */
    private function byReferenceForm(Node $node, Expr $target, array $writes, array $stmts, AnalysisScope $scope): ?array
    {
        $path = $this->keyPath($target);

        if ($node instanceof Foreach_ && $node->byRef && $node->expr === $target) {
            $element = $node->valueVar instanceof Variable && is_string($node->valueVar->name) ? $node->valueVar->name : null;

            if ($element === null || $path === null) {
                return $this->form(ByReferenceEffect::Replaces);
            }

            // After the loop the variable still points at the last element, so a later write lands in it, as one before
            // the loop does when a loop around both runs it again; unset() breaks the reference.
            $loops = new NodeFinder()->find($stmts, fn (Node $loop): bool => $loop !== $node
                && ($loop instanceof For_ || $loop instanceof Foreach_ || $loop instanceof While_ || $loop instanceof Do_)
                && $this->encloses($loop, $node));
            $values = $this->elementValues(array_values(array_filter(
                $writes,
                fn (array $write): bool => $write[0] === $element && $write[1] !== $node && ! $write[1] instanceof Unset_
                    && ($write[1]->getStartFilePos() > $node->getStartFilePos()
                        || array_any($loops, fn (Node $loop): bool => $this->encloses($loop, $write[1]))),
            )));

            return $values === null
                ? $this->form(ByReferenceEffect::Replaces)
                : $this->form(ByReferenceEffect::WritesElements, $values, $path);
        }

        if (! $node instanceof CallLike) {
            return null;
        }

        if ($path === null) {
            return $this->form(ByReferenceEffect::Replaces);
        }

        $effect = resolve(ByReferenceArguments::class)->effect($node, $target);

        return match ($effect) {
            ByReferenceEffect::Appends => $this->appendedForm($node, $target, $path),
            ByReferenceEffect::WritesElements, ByReferenceEffect::WritesLeaves => $this->walkedForm($node, $effect, $path, $scope),
            default => $this->form($effect, [], $path),
        };
    }

    /**
     * The literal keys a target reaches into its variable through, outermost first: none for the variable itself, and
     * null for a computed or appended key, or anything but a variable at the root.
     *
     * @return list<string>|null
     */
    private function keyPath(Expr $target): ?array
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
    private function appendedForm(CallLike $call, Expr $target, array $path): array
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
            ? $this->form(ByReferenceEffect::Replaces)
            : $this->form(ByReferenceEffect::Appends, $values, $path);
    }

    /**
     * What array_walk() or array_walk_recursive() leaves: every element, when its callback takes none by reference,
     * else each element, or each leaf, with the values its callback writes into it, read only from a closure written
     * at the call and read in that closure's own scope.
     *
     * @param  list<string>  $path
     * @return ByReferenceForm
     */
    private function walkedForm(CallLike $call, ByReferenceEffect $effect, array $path, AnalysisScope $scope): array
    {
        $callback = CallArguments::fromNames($call->getArgs(), ['array', 'callback', 'arg'])->named('callback')?->value;

        if (! $callback instanceof ClosureExpr && ! $callback instanceof ArrowFunction) {
            return $this->form(ByReferenceEffect::Replaces);
        }

        $parameter = $callback->params[0] ?? null;

        if ($parameter === null || ! $parameter->byRef) {
            return $this->form(ByReferenceEffect::KeepsAny, [], $path);
        }

        $element = ! $parameter->variadic && $parameter->var instanceof Variable && is_string($parameter->var->name)
            ? $parameter->var->name
            : null;
        $body = $callback instanceof ClosureExpr ? $callback->stmts : [$callback->expr];
        $values = $element === null ? null : $this->elementValues(array_values(array_filter(
            $this->collectVariableWrites($body, $scope->subjectReflection, $scope->declaringFileClass),
            fn (array $write): bool => $write[0] === $element,
        )));

        return $values === null ? $this->form(ByReferenceEffect::Replaces) : $this->form($effect, $values, $path, $callback);
    }

    /**
     * The values writes to an element's variable put in the element, or null when one is a write the scan cannot type:
     * anything but an assignment, an arithmetic or `.=` operator, `??=`, `++` or `--` of the variable itself. An
     * increment stands for its own value, and an operator for the operation it performs.
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
                $node instanceof AssignOp => $this->compoundValue($node),
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
    private function compoundValue(AssignOp $assign): ?Expr
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
    private function form(
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

    /**
     * The statements of the innermost `if`, `elseif`, `else` or `case` block holding a node that always leaves the
     * function, or null when none does. A block inside a nested function leaves that function, and one inside a `try`
     * whose `catch` or `finally` can run after it may resume past it.
     *
     * @param  array<Node>  $stmts
     * @return non-empty-list<Node\Stmt>|null
     */
    private function exitingBlock(array $stmts, Node $node): ?array
    {
        $finder = new NodeFinder;
        $enclosures = $finder->find($stmts, fn (Node $around): bool => $around instanceof ClosureExpr
            || $around instanceof ArrowFunction || $around instanceof Function_ || $around instanceof ClassLike
            || ($around instanceof TryCatch && ($around->catches !== [] || $around->finally !== null)));
        $innermost = null;

        foreach ($finder->find($stmts, fn (Node $owner): bool => $owner instanceof If_ || $owner instanceof ElseIf_
            || $owner instanceof Else_ || $owner instanceof Case_) as $owner) {
            /** @var If_|ElseIf_|Else_|Case_ $owner */
            $block = array_values($owner->stmts);

            if ($block !== []
                && array_any($block, fn (Node\Stmt $stmt): bool => $this->encloses($stmt, $node))
                && ($innermost === null || $block[0]->getStartFilePos() > $innermost[0]->getStartFilePos())
                && ! array_any($enclosures, fn (Node $around): bool => $this->encloses($around, $owner))
                && $this->alwaysExits($block)
            ) {
                $innermost = $block;
            }
        }

        return $innermost;
    }

    /**
     * Record a gap for a name, joining the values of another write that opens the same span, since either may land.
     *
     * @param  BindingGap  $gap
     */
    private function addGap(AnalysisScope $scope, string $name, array $gap): void
    {
        foreach ($scope->bindingGaps[$name] ?? [] as $index => $known) {
            if ($known[0] === $gap[0] && $known[1] === $gap[1]) {
                $scope->bindingGaps[$name][$index] = [
                    $gap[0],
                    $gap[1],
                    $known[2] === null || $gap[2] === null ? null : [...$known[2], ...$gap[2]],
                    true,
                    $known[4] && $gap[4],
                ];

                return;
            }
        }

        $scope->bindingGaps[$name][] = $gap;
    }

    /**
     * Walk one list of statements for the values a name may hold, from a state it holds past $from, recording a gap for
     * the reads between the statements that write it, and give the state past the list; null where a parameter's walk
     * stops.
     *
     * @param  array<Node\Stmt>  $stmts  the list walked
     * @param  array<Node\Stmt>  $body  the whole body, which a block that always exits leaves
     * @param  VariableWrites  $writes  the name's own writes
     * @param  Node|null  $ender  the write that ended the name's binding, if one did
     * @param  JoinedState  $state  the values the name may hold, null for none the scan knows, whether the binding it
     *                              came in with may still hold, whether a receiver keeps that binding, and in a
     *                              parameter's walk the values nested writes added, null in a local's
     * @return JoinedState|null
     */
    private function joinStatements(
        string $name,
        array $stmts,
        array $body,
        array $writes,
        ?Node $ender,
        array $state,
        ?int $from,
        AnalysisScope $scope,
    ): ?array {
        $after = $from;

        foreach ($stmts as $stmt) {
            $own = array_values(array_filter($writes, fn (array $write): bool => $this->encloses($stmt, $write[1])));
            $assigned = $own === [] ? null : $this->wholeAssignment($stmt, $own);

            // A write in a block that always exits reaches no read past it, which only a known value needs checked; a
            // `try` resumes past its blocks.
            if ($assigned === null && $state[0] !== null && ! $stmt instanceof TryCatch) {
                $own = array_values(array_filter($own, fn (array $write): bool => $this->exitingBlock($body, $write[1]) === null));
            }

            if ($own === []) {
                continue;
            }

            // The value a statement assigns reads what the name held before it, so that span runs through it.
            $this->addJoinedGap($scope, $name, $after, $assigned === null ? $stmt->getStartFilePos() - 1 : $stmt->getEndFilePos(), $state);
            $state = match (true) {
                $stmt instanceof TryCatch => $this->joinTry($name, $stmt, $body, $writes, $ender, $state, $scope),
                // Past its own `$x = …;` a value read takes what it wrote; a class alone is endBindingsAtWrites()'s.
                $assigned !== null => ($state[2] && ! $this->bindsValue($name, $scope))
                    ? null
                    : [[$assigned], false, $state[2] && $own[0][1] !== $ender, $this->nothingNested($state)],
                default => $this->joinNested($own, $state),
            };

            if ($state === null) {
                return null;
            }

            // Past a write that ended the binding, its own read of the name finds none, so a receiver stops keeping it.
            $state[2] = $state[2] && ($scope->bindingEnds[$name] ?? PHP_INT_MAX) >= $stmt->getEndFilePos();
            $after = $stmt->getEndFilePos();
        }

        if ($stmts !== []) {
            $this->addJoinedGap($scope, $name, $after, $stmts[array_key_last($stmts)]->getEndFilePos(), $state);
        }

        return $state;
    }

    /**
     * The value a statement assigns when it is one `$x = …;`, the name's only write in it; else null.
     *
     * @param  non-empty-list<NamedWrite>  $writes  the statement's writes of the name
     */
    private function wholeAssignment(Node\Stmt $stmt, array $writes): ?Expr
    {
        [, $node, $target] = $writes[0];

        return count($writes) === 1 && $this->isStatementOf([$stmt], $node) && $node instanceof Assign
            && $this->writeKind($node, $target) === VariableWrite::Replaces
                ? $node->expr
                : null;
    }

    /**
     * The state past a `try`: what its body, or a catch it resumes in, leaves, then what its finally leaves of that. A
     * catch starts from what the name held before the `try` or any value the body assigned before it threw.
     *
     * @param  array<Node\Stmt>  $body
     * @param  VariableWrites  $writes  the name's own writes
     * @param  JoinedState  $state
     * @return JoinedState|null
     */
    private function joinTry(
        string $name,
        TryCatch $try,
        array $body,
        array $writes,
        ?Node $ender,
        array $state,
        AnalysisScope $scope,
    ): ?array {
        if (array_any($try->catches, fn (Catch_ $catch): bool => $catch->var?->name === $name)) {
            return $state[2] ? null : [null, false, false, $this->nothingNested($state)];
        }

        $thrown = $this->joinNested(array_values(array_filter(
            $writes,
            fn (array $write): bool => array_any($try->stmts, fn (Node\Stmt $stmt): bool => $this->encloses($stmt, $write[1])),
        )), $state);
        $left = [$this->joinStatements($name, $try->stmts, $body, $writes, $ender, $state, $this->blockStart($try->stmts), $scope)];

        if ($thrown !== null && $try->stmts !== []) {
            $thrown[2] = $thrown[2] && ($scope->bindingEnds[$name] ?? PHP_INT_MAX) >= $try->stmts[array_key_last($try->stmts)]->getEndFilePos();
        }

        foreach ($try->catches as $catch) {
            $left[] = $thrown === null
                ? null
                : $this->joinStatements($name, $catch->stmts, $body, $writes, $ender, $thrown, $this->blockStart($catch->stmts), $scope);
        }

        if (in_array(null, $left, true)) {
            return null;
        }

        /** @var non-empty-list<JoinedState> $left */
        $joined = [
            array_any($left, fn (array $state): bool => $state[0] === null)
                ? null
                : $this->joinValues(...array_map(fn (array $state): array => $state[0] ?? [], $left)),
            array_any($left, fn (array $state): bool => $state[1]),
            array_all($left, fn (array $state): bool => $state[2]),
            $state[3] === null ? null : $this->joinValues(...array_map(fn (array $state): array => $state[3] ?? [], $left)),
        ];

        return $try->finally === null
            ? $joined
            : $this->joinStatements($name, $try->finally->stmts, $body, $writes, $ender, $joined, $this->blockStart($try->finally->stmts), $scope);
    }

    /**
     * The state past a statement whose writes of the name lie nested in it: the values before it and those its
     * assignments add, or none the scan knows where one is a write of another kind, which stops a parameter's walk.
     *
     * @param  VariableWrites  $writes  the statement's writes of the name
     * @param  JoinedState  $state
     * @return JoinedState|null
     */
    private function joinNested(array $writes, array $state): ?array
    {
        $values = [];

        foreach ($writes as [, $node, $target]) {
            if ($this->writeKind($node, $target) !== VariableWrite::Replaces || ! $node instanceof Assign) {
                return $state[2] ? null : [null, false, false, $this->nothingNested($state)];
            }

            $values[] = $node->expr;
        }

        return [
            $state[0] === null ? null : $this->joinValues($state[0], $values),
            $state[1],
            $state[2],
            $state[3] === null ? null : $this->joinValues($state[3], $values),
        ];
    }

    /**
     * The values of several lists, each expression once.
     *
     * @param  list<Expr>  ...$lists
     * @return list<Expr>
     */
    private function joinValues(array ...$lists): array
    {
        $joined = [];

        foreach ($lists as $list) {
            foreach ($list as $value) {
                if (! in_array($value, $joined, true)) {
                    $joined[] = $value;
                }
            }
        }

        return $joined;
    }

    /**
     * The nested values a state tracks past a write replacing the name: none in a parameter's walk, null in a local's.
     *
     * @param  JoinedState  $state
     * @return list<Expr>|null
     */
    private function nothingNested(array $state): ?array
    {
        return $state[3] === null ? null : [];
    }

    /**
     * Record a gap for the reads between two offsets that a name's joined values hold for, unless they hold nothing but
     * the binding it came in with and no end of that binding comes before $end.
     *
     * @param  JoinedState  $state
     */
    private function addJoinedGap(AnalysisScope $scope, string $name, ?int $after, int $end, array $state): void
    {
        if ($after === null || $end <= $after || $state[0] === null
            || ($state[0] === [] && (! $state[1] || ($scope->bindingEnds[$name] ?? PHP_INT_MAX) >= $end))
        ) {
            return;
        }

        $gap = [$after, $end, $state[0], $state[1], $state[2]];
        $scope->bindingGaps[$name][] = $state[3] === null || $state[3] === [] ? $gap : [...$gap, $state[3]];
    }

    /**
     * The offset just before a block's first statement, past which what the block's reads see holds; null for none.
     *
     * @param  array<Node\Stmt>  $block
     */
    private function blockStart(array $block): ?int
    {
        return $block === [] ? null : $block[0]->getStartFilePos() - 1;
    }

    /**
     * Where a write ends a binding among a block's statements: past its own statement when that statement is the
     * assignment itself, else where the statement holding it starts. Null when no statement holds it.
     *
     * @param  array<Node>  $block
     */
    private function endWithin(array $block, Node $node): ?int
    {
        foreach ($block as $stmt) {
            if ($this->encloses($stmt, $node)) {
                return $stmt instanceof ExpressionStmt && $stmt->expr === $node && ($node instanceof Assign || $node instanceof AssignOp)
                    ? $stmt->getEndFilePos()
                    : $stmt->getStartFilePos() - 1;
            }
        }

        return null;
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
     * Whether a name binds a value, alone or beside the class a receiver reads, which a value read takes.
     */
    private function bindsValue(string $name, AnalysisScope $scope): bool
    {
        return isset($scope->varValueBindings[$name]) || isset($scope->closureParamExprBindings[$name]);
    }

    /**
     * Whether a name's binding is a value alone: no class, declared class or collection element, and no expression the
     * receiver resolver names a class for, such as a relation read or a model no row backs yet.
     */
    private function bindsValueOnly(string $name, AnalysisScope $scope): bool
    {
        $bound = $scope->closureParamExprBindings[$name] ?? null;

        return ! isset($scope->varModelBindings[$name])
            && ! isset($scope->varClassBindings[$name])
            && ! isset($scope->requestVarNames[$name])
            && ! isset($scope->varDeclaredBindings[$name])
            && ! isset($scope->varCollectionBindings[$name])
            && ($bound === null || $scope->resolvingBinding(
                $name,
                $bound,
                fn (): bool => resolve(ReceiverClassResolver::class)->resolve($bound, $scope) === null,
            ) === true);
    }

    /**
     * What a write node does to the variable a target of it names; the one place a pass that ends, gaps or joins a
     * binding tells writes apart.
     */
    private function writeKind(Node $node, Expr $target): VariableWrite
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
            // `$$k = …` writes the variable `$k` names, never `$k`.
            ! $target instanceof Variable || ! is_string($target->name) => VariableWrite::MemberOrOffset,
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
