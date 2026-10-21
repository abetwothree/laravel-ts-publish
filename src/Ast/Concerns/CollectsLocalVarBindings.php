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
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use PhpParser\ConstExprEvaluationException;
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
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
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
 * @phpstan-type NamedWrite = array{string, Node, Expr, VariableWrite}
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

    /** The Model methods that write attributes, wherever a body calls them. */
    private const array ATTRIBUTE_WRITERS = ['fill', 'forceFill', 'setAttribute', 'setRawAttributes', 'offsetSet'];

    /**
     * Seed a body once its parameters are bound: its own locals, top-level and block-scoped, the end of each binding it
     * came in with at the write that replaces it, then its guards, all from one scan of its writes.
     *
     * A local shadows an outer binding of its name however often the body writes it; a method body starts with none.
     * A by-reference call that keeps a value in some form writes none: its form is recorded for the read to check.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  ClosureExpr|null  $closure  the closure whose body the statements are, whose writes end what it captured
     */
    protected function seedBody(array $stmts, AnalysisScope $scope, ?ClosureExpr $closure = null): void
    {
        $writes = $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass);
        [$replacing, $kept] = $this->partitionWrites($writes, $stmts, $scope);
        $binding = $this->bindingWrites($replacing, $scope);

        foreach ($binding as [$name]) {
            unset($scope->localVarBindings[$name], $scope->varDocBindings[$name], $scope->byReferenceForms[$name]);

            // An outer block local's span may cover this body, as a closure in that block. A bound name's gap holds the
            // value its block wrote, which a closure capturing it still reads before its own write.
            if (! $scope->bindsName($name)) {
                unset($scope->bindingGaps[$name]);
            }
        }

        if ($closure !== null) {
            $this->endCapturedBindings($closure, $stmts, $scope, $binding);
        }

        // A name no write but preg_match() reaches holds the matches past a call that surely replaced what it held.
        $written = array_column([...$binding, ...array_filter(
            $kept,
            fn (array $write): bool => $write[1]['effect'] !== ByReferenceEffect::WritesMatches,
        )], 0);

        foreach ($kept as [$name, $form]) {
            if ($form['effect'] !== ByReferenceEffect::KeepsAny) {
                $scope->byReferenceForms[$name][] = [...$form, 'sole' => ! in_array($name, $written, true)];
            }
        }

        $parked = null;
        $this->collectLocalVarBindings($stmts, $scope, $binding, $parked);
        $this->bindBlockLocals($stmts, $scope, $binding, $parked);
        // After the locals: keepsBinding() reads the `@var` spans they record.
        $ended = $this->endBindingsAtWrites($stmts, $scope, $writes);
        $this->bindJoinedLocals($stmts, $scope, $binding, $ended);
        // A call that keeps a value leaves it an array or a number, so it ends no guard's class or non-null proof; a
        // member write may null a member a guard proves.
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
     * @param  list<ArrowFunction|ClosureExpr>|null  $parked  the body's closures that never run, once read
     */
    protected function collectLocalVarBindings(
        array $stmts,
        AnalysisScope $scope,
        ?array $writes = null,
        ?array &$parked = null,
    ): void {
        $writes ??= $this->bindingWrites($this->partitionWrites(
            $this->collectVariableWrites($stmts, $scope->subjectReflection, $scope->declaringFileClass),
            $stmts,
            $scope,
        )[0], $scope);
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
            $this->markFilled($stmt->expr->expr, $name, $stmts, $writes, $scope, $parked);

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
     * @param  list<ArrowFunction|ClosureExpr>|null  $parked  the body's closures that never run, once read
     */
    protected function bindBlockLocals(array $stmts, AnalysisScope $scope, array $writes, ?array &$parked = null): void
    {
        foreach ($stmts as $stmt) {
            foreach ($this->innerBlocks($stmt) as $block) {
                $this->bindBlock($block, $stmts, $scope, $writes, $parked);
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

            if (array_any($own, fn (array $write): bool => $write[3]->makesReference())) {
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

        foreach ($writes as [$name, $node, $target, $kind]) {
            // A write past an end already recorded cannot move it earlier.
            if (! $scope->bindsName($name)
                || (isset($scope->bindingEnds[$name]) && $node->getStartFilePos() > $scope->bindingEnds[$name])
                || ! $this->replacesVariable($target, $name)
            ) {
                continue;
            }

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
     * An assignment, an increment, a `foreach` target and a `use` also count the variable a member or key they write
     * goes through; the rest count just the variable they name or key into, so `unset($o->p)` writes nothing.
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
     * @return VariableWrites
     */
    protected function writesFrom(string $name, int $offset, array $writes): array
    {
        return array_values(array_filter(
            $writes,
            fn (array $write): bool => $write[0] === $name && $write[1]->getEndFilePos() >= $offset,
        ));
    }

    /**
     * Mark the `new` a variable is bound to as holding the attributes its body fills, before a read or after it, but
     * not past a later write in the list holding that write: Laravel then sends what was written. An attribute writer
     * or calls on it as a statement fill every one; a member or key write fills that column.
     *
     * @param  array<Node>  $body
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     * @param  list<ArrowFunction|ClosureExpr>|null  $parked  the body's closures that never run, read here once
     */
    protected function markFilled(
        Expr $bound,
        string $name,
        array $body,
        array $writes,
        AnalysisScope $scope,
        ?array &$parked = null,
    ): void {
        if (! $bound instanceof New_ || $bound->args !== []) {
            return;
        }

        $from = $bound->getEndFilePos();
        $cuts = [[PHP_INT_MIN, $from]];

        foreach ($writes as [$written, $node, , $kind]) {
            // A by-reference `use` imports the variable; a write inside that closure is a write of its own.
            if ($written === $name && $node->getStartFilePos() > $from
                && $kind !== VariableWrite::MemberOrOffset && $kind !== VariableWrite::InClosure
            ) {
                $cuts[] = [$node->getStartFilePos(), $this->listEnd($this->holdingList($body, $node))];
            }
        }

        $models = resolve(ReceiverClassResolver::class)->resolve($bound, $scope)?->models() ?? [];
        $fill = fn (Node $node): true|string|null => $this->fills($node, $name, $models, $scope);
        $members = [];

        $parked ??= $this->parkedClosures($body);

        if ($this->fillsWithin($body, $name, $fill, $cuts, $parked, $members)) {
            $bound->setAttribute(ReceiverClassResolver::HOLDS_ATTRIBUTES, true);
        } elseif ($members !== []) {
            $bound->setAttribute(ReceiverClassResolver::FILLED_MEMBERS, array_values(array_unique($members)));
        }
    }

    /**
     * Mark each parameter default `new` that AnalysisScope::bindUnpassedParameters() bound as holding attributes where
     * the closure's body fills it.
     */
    protected function markFilledDefaults(Expr $closure, AnalysisScope $scope): void
    {
        if (! $closure instanceof ArrowFunction && ! $closure instanceof ClosureExpr) {
            return;
        }

        $body = $closure->getStmts();
        $writes = null;
        $parked = null;

        foreach ($closure->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name) && $param->default instanceof New_
                && ($scope->closureParamExprBindings[$param->var->name] ?? null) === $param->default
            ) {
                $writes ??= $this->collectVariableWrites($body, $scope->subjectReflection, $scope->declaringFileClass);
                $this->markFilled($param->default, $param->var->name, $body, $writes, $scope, $parked);
            }
        }
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

        foreach ($this->writesFrom($name, $assignment->getStartFilePos(), $writes) as [, $write]) {
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
     *
     * @param  bool  $nested  the assignment is nested in a block, which a join may carry its value past
     */
    private function bindDeclaredSpan(
        ExpressionStmt $assignment,
        Assign $assign,
        string $name,
        int $after,
        ?int $before,
        AnalysisScope $scope,
        bool $nested = false,
    ): void {
        if ($assignment->getDocComment() === null) {
            return;
        }

        $tag = resolve(PropertyDocblockTypeReader::class)
            ->extractVarTag($assignment->getDocComment()->getText(), $scope->declaringFileClass);

        if ($tag === null || ($tag[1] !== null && $tag[1] !== $name)) {
            return;
        }

        $binding = [
            'type' => $tag[0],
            'context' => $scope->declaringFileClass,
            'after' => $after,
            'before' => $before,
            'expr' => $assign->expr,
        ];
        $scope->varDocBindings[$name][] = $nested ? [...$binding, 'nested' => true] : $binding;
    }

    /**
     * Bind the assignments of one nested block, then those of the blocks its statements hold.
     *
     * @param  array<Node\Stmt>  $block
     * @param  array<Node\Stmt>  $body  the whole body, for the writes that fill a `new` model a local holds
     * @param  VariableWrites  $writes
     * @param  list<ArrowFunction|ClosureExpr>|null  $parked  the body's closures that never run, once read
     */
    private function bindBlock(array $block, array $body, AnalysisScope $scope, array $writes, ?array &$parked): void
    {
        foreach ($block as $stmt) {
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && is_string($stmt->expr->var->name)
            ) {
                $this->markFilled($stmt->expr->expr, $stmt->expr->var->name, $body, $writes, $scope, $parked);
                $this->bindBlockAssignment($stmt, $stmt->expr, $stmt->expr->var->name, $block, $scope, $writes);
            }

            foreach ($this->innerBlocks($stmt) as $inner) {
                $this->bindBlock($inner, $body, $scope, $writes, $parked);
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
        $bound = $scope->bindsName($name);

        // With nothing of its block past it, the `@var` still types the value a local's join carries past the block. A
        // bound name's would end its binding in keepsBinding(): a member only the old class types would read unknown.
        if ($end === null || ($end <= $after && $bound)) {
            return;
        }

        $this->bindDeclaredSpan($statement, $assign, $name, $after, $end + 1, $scope, nested: true);

        if ($end > $after && ! $bound) {
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

        foreach ($writes as [$written, $node, , $kind]) {
            if ($written !== $name) {
                continue;
            }

            if ($kind->makesReference()) {
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
     * End each outer binding of a name a closure's body assigns, past that body's first `$x = …` of it, unless the
     * closure declares the name or imports it by reference: from there the name is the closure's own variable.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     */
    private function endCapturedBindings(ClosureExpr $closure, array $stmts, AnalysisScope $scope, array $writes): void
    {
        $own = [
            ...array_map(
                fn (Param $param): mixed => $param->var instanceof Variable ? $param->var->name : null,
                $closure->params,
            ),
            ...array_map(fn (ClosureUse $use): mixed => $use->byRef ? $use->var->name : null, $closure->uses),
        ];

        foreach ($writes as [$name, $node, , $kind]) {
            $end = $this->endWithin($stmts, $node);

            if ($kind !== VariableWrite::Replaces || $end === null || in_array($name, $own, true)
                || ! $scope->bindsName($name) || ($scope->bindingEnds[$name] ?? PHP_INT_MAX) <= $end
            ) {
                continue;
            }

            $scope->bindingEnds[$name] = $end;
            $this->clipSpans($scope, $name, $end);
        }
    }

    /**
     * Cut a name's binding gaps and guards off at an offset, dropping each left holding no read.
     */
    private function clipSpans(AnalysisScope $scope, string $name, int $end): void
    {
        $gaps = [];
        $guards = [];

        foreach ($scope->bindingGaps[$name] ?? [] as $gap) {
            $gap[1] = min($gap[1], $end);
            $gaps = $gap[0] < $gap[1] ? [...$gaps, $gap] : $gaps;
        }

        foreach ($scope->varGuardBindings[$name] ?? [] as $guard) {
            $guard['before'] = min($guard['before'] ?? $end, $end);
            $guards = $guard['after'] < $guard['before'] ? [...$guards, $guard] : $guards;
        }

        unset($scope->bindingGaps[$name], $scope->varGuardBindings[$name]);

        if ($gaps !== []) {
            $scope->bindingGaps[$name] = $gaps;
        }

        if ($guards !== []) {
            $scope->varGuardBindings[$name] = $guards;
        }
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
     * The writes that replace what a variable holds, but each through a member or an offset of a variable holding only
     * models: Laravel stores the write as an attribute of the same model, so the binding stands.
     *
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     * @return VariableWrites
     */
    private function bindingWrites(array $writes, AnalysisScope $scope): array
    {
        $holdsModels = [];

        foreach ($writes as [$name, , , $kind]) {
            if ($kind === VariableWrite::MemberOrOffset && $name !== 'this') {
                $holdsModels[$name] ??= $this->holdsOnlyModels($name, $writes, $scope);
            }
        }

        return array_values(array_filter(
            $writes,
            fn (array $write): bool => $write[3] !== VariableWrite::MemberOrOffset
                || ! ($holdsModels[$write[0]] ?? false),
        ));
    }

    /**
     * Whether every value a name holds in the body is a model, which each write through it leaves holding it: the
     * binding it came in with, if any, and each value the body assigns it, where no write of another kind gives it a
     * value the scan cannot read, and each member or key write stores a value its column holds.
     *
     * @param  VariableWrites  $writes  the body's writes that replace a variable
     */
    private function holdsOnlyModels(string $name, array $writes, AnalysisScope $scope): bool
    {
        $declared = $scope->varClassBindings[$name] ?? $scope->varDeclaredBindings[$name] ?? null;
        $bound = $scope->closureParamExprBindings[$name] ?? $scope->localVarBindings[$name] ?? null;
        $classes = match (true) {
            isset($scope->varModelBindings[$name]) => [$scope->varModelBindings[$name]],
            $declared !== null => $declared,
            $bound === null && $scope->bindsName($name) => null,
            default => [],
        };
        $values = $classes === [] && $bound !== null ? [$bound] : [];
        $through = [];

        foreach ($writes as [$written, $node, , $kind]) {
            if ($written === $name && $kind === VariableWrite::MemberOrOffset) {
                $through[] = $node;
            } elseif ($written === $name && ($kind === VariableWrite::Replaces || $kind === VariableWrite::Coalesces)
                && ($node instanceof Assign || $node instanceof CoalesceAssign)
            ) {
                $values[] = $node->expr;
            } elseif ($written === $name) {
                return false;
            }
        }

        $nullable = ValueResult::hasNullArm($scope->varValueBindings[$name]['type'] ?? '');

        foreach ($values as $value) {
            $held = $classes === null ? null : resolve(ReceiverClassResolver::class)->resolve($value, $scope);
            $classes = $held === null ? null : [...$classes, ...$held->classes];
            $nullable = $nullable || $held?->shortCircuits || new NodeFinder()->findFirst(
                $value,
                fn (Node $node): bool => $node instanceof ConstFetch && $node->name->toLowerString() === 'null',
            ) !== null;
        }

        $models = array_values(array_unique($classes ?? []));

        // A key write on null makes the variable an array.
        return $models !== [] && array_all($models, fn (string $class): bool => is_a($class, Model::class, true))
            && array_all($through, fn (Node $node): bool => ! ($nullable && ($this->writtenMember($node)[2] ?? false))
                && $this->keepsModelThrough($node, $name, $models, $scope));
    }

    /**
     * Whether a write through a member or key of a variable holding models stores a value its column holds, on those
     * models or on the one model each relation of them the write goes through holds.
     *
     * @param  list<class-string>  $models
     */
    private function keepsModelThrough(Node $node, string $name, array $models, AnalysisScope $scope): bool
    {
        [$holder, $member] = $this->writtenMember($node) ?? [null, '', false];
        $relation = $holder instanceof PropertyFetch && $holder->var instanceof Variable && $holder->var->name === $name
            && $holder->name instanceof Identifier ? $holder->name->toString() : null;
        $relations = resolve(ModelAttributeResolver::class);
        $related = $relation === null ? [] : array_map(
            fn (string $model): ?string => $relations->resolveRelation($model, $relation)['modelFqcn'],
            $models,
        );
        $holds = match (true) {
            $holder instanceof Variable && $holder->name === $name => $models,
            $related !== [] && ! in_array(null, $related, true) => $related,
            default => [],
        };

        return $this->storesWithinColumn($node, $member, $holds, $scope);
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
                // Past its own `$x = …;` a value read takes what it wrote, a model's where that is a typed value naming
                // no class; any other class alone is endBindingsAtWrites()'s.
                $assigned !== null => ($state[2] && ! $this->bindsValue($name, $scope)
                    && ! $this->replacesModelValue($name, $assigned, $scope))
                    ? null
                    : [
                        [$assigned],
                        false,
                        $state[2] && $own[0][1] !== $ender && $this->keepsReceiverPast($name, $assigned, $scope),
                        $this->nothingNested($state),
                    ],
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
        [, $node, , $kind] = $writes[0];

        return count($writes) === 1 && $this->isStatementOf([$stmt], $node) && $node instanceof Assign
            && $kind === VariableWrite::Replaces
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

        foreach ($writes as [, $node, , $kind]) {
            if ($kind !== VariableWrite::Replaces || ! $node instanceof Assign) {
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
     * Whether a write replaces a model binding's value: a typed value naming no class, which a read past it takes. An
     * untyped one, such as a helper's that may hand the model back, keeps the binding (O11's class half).
     */
    private function replacesModelValue(string $name, Expr $written, AnalysisScope $scope): bool
    {
        return isset($scope->varModelBindings[$name])
            && resolve(ReceiverClassResolver::class)->resolve($written, $scope) === null
            && $this->typedAtSeed($written, $scope);
    }

    /**
     * Whether a receiver keeps a name's binding past its own `$x = …;`: beside a value binding, or past a model's
     * write of a value the scan cannot type.
     */
    private function keepsReceiverPast(string $name, Expr $written, AnalysisScope $scope): bool
    {
        return $this->bindsValue($name, $scope) || ! $this->typedAtSeed($written, $scope);
    }

    /**
     * Whether the scan types a value without the engine, so a model's receiver need not outlast a write of it: a
     * constant, a scalar cast, a string built by `.` or interpolation, or a column a model holds.
     */
    private function typedAtSeed(Expr $value, AnalysisScope $scope): bool
    {
        $member = ($value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch)
            && $value->name instanceof Identifier ? $value->name->toString() : null;
        $held = $member === null ? null : resolve(ReceiverClassResolver::class)->resolve($value->var, $scope);
        $models = $held?->models() ?? [];

        return $this->constantType($value, $scope) !== null
            || ($value instanceof Cast && ! $value instanceof Cast\Object_ && ! $value instanceof Cast\Unset_)
            || $value instanceof BinaryOp\Concat || $value instanceof InterpolatedString
            || ($models !== [] && array_all($models, fn (string $model): bool => resolve(ModelAttributeResolver::class)
                ->resolveAttribute($model, (string) $member)['type'] !== 'unknown'));
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
     * What a write node does to the variable a target of it names, read once as the scan records the write; the one
     * place writes are told apart.
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
                        $writes[] = [$name, $node, $target, $this->writeKind($node, $target)];
                    }
                }
            }

            foreach ($this->rootedWriteTargets($node, $subject, $declaration) as $target) {
                $name = $this->rootVariable($target);

                if ($name !== null && $this->reachesBody($name, $enclosing)) {
                    $writes[] = [$name, $node, $target, $this->writeKind($node, $target)];
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
     * The expressions an assignment, an increment, a `foreach` target or a by-reference `use` writes to, each counted
     * for the variables writtenVariables() names.
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
     * The names of the variables a write target writes: the variable, each a destructuring list spells, or the one a
     * member or offset write goes through. A variable a key or a member name reads is not written, and neither is the
     * one a variable variable names.
     *
     * @return list<string>
     */
    private function writtenVariables(Expr $target): array
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            return array_merge(...array_map(
                fn (?ArrayItem $item): array => $item === null ? [] : $this->writtenVariables($item->value),
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
     * Whether nodes fill every attribute of the model a variable holds, adding each column one fills alone to $members,
     * outside a nested class or function, a closure that never runs or has a variable of that name of its own, and each
     * span cut off: the reads before the model, and those past a later write. A node the source does not place is read.
     *
     * @param  array<mixed>|Node  $nodes
     * @param  Closure(Node): (true|string|null)  $fill  what one node fills
     * @param  list<array{int, int}>  $cuts
     * @param  list<ArrowFunction|ClosureExpr>  $parked  the closures that never run
     * @param  list<string>  $members
     */
    private function fillsWithin(
        array|Node $nodes,
        string $name,
        Closure $fill,
        array $cuts,
        array $parked,
        array &$members,
    ): bool {
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if (is_array($node) && $this->fillsWithin($node, $name, $fill, $cuts, $parked, $members)) {
                return true;
            }

            $start = $node instanceof Node ? $node->getStartFilePos() : -1;
            $cut = fn (int $at): bool => array_any($cuts, fn (array $cut): bool => $at > $cut[0] && $at <= $cut[1]);

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_
                || ($start >= 0 && $cut($start) && $cut($node->getEndFilePos()))
                || (($node instanceof ClosureExpr || $node instanceof ArrowFunction)
                    && ! $this->sharesVariable($node, $name, $parked))
            ) {
                continue;
            }

            $filled = $start < 0 || $cut($start) ? null : $fill($node);

            if ($filled === true) {
                return true;
            }

            $members = $filled === null ? $members : [...$members, $filled];

            foreach ($node->getSubNodeNames() as $subNode) {
                $child = $node->{$subNode};

                if (($child instanceof Node || is_array($child))
                    && $this->fillsWithin($child, $name, $fill, $cuts, $parked, $members)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The statement list holding a node most closely: the innermost block's, else the given list.
     *
     * @param  array<Node>  $stmts
     * @return array<Node>
     */
    private function holdingList(array $stmts, Node $node): array
    {
        foreach ($stmts as $stmt) {
            $inner = $stmt instanceof Node\Stmt && $this->encloses($stmt, $node)
                ? array_find($this->innerBlocks($stmt), fn (array $block): bool => array_any(
                    $block,
                    fn (Node\Stmt $held): bool => $this->encloses($held, $node),
                ))
                : null;

            if ($inner !== null) {
                return $this->holdingList($inner, $node);
            }
        }

        return $stmts;
    }

    /**
     * The offset a statement list ends at; one the source does not place reaches past every offset.
     *
     * @param  array<Node>  $stmts
     */
    private function listEnd(array $stmts): int
    {
        $end = $stmts === [] ? -1 : $stmts[array_key_last($stmts)]->getEndFilePos();

        return $end < 0 ? PHP_INT_MAX : $end;
    }

    /**
     * What one node fills of the model a variable holds: every attribute (true) where it calls an attribute writer on
     * it, or a chain of methods on it as a statement, such as a relation's associate(); the column a member or key
     * write of it stores a value that column holds in; else nothing.
     *
     * @param  list<class-string<Model>>  $models  the models the variable may hold
     */
    private function fills(Node $node, string $name, array $models, AnalysisScope $scope): true|string|null
    {
        [$holder, $member] = $this->writtenMember($node) ?? [null, '', false];

        return match (true) {
            $holder !== null => $holder instanceof Variable && $holder->name === $name
                && $this->storesWithinColumn($node, $member, $models, $scope) ? $member : null,
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $node->name instanceof Identifier
                && in_array($node->name->toString(), self::ATTRIBUTE_WRITERS, true)
                && $node->var instanceof Variable && $node->var->name === $name ?: null,
            default => $node instanceof ExpressionStmt
                && ($node->expr instanceof MethodCall || $node->expr instanceof NullsafeMethodCall)
                && $this->callsOn($node->expr->var, $name) ?: null,
        };
    }

    /**
     * The holder and the column a write through a member or a literal key stores into, and whether a key names it;
     * null for any other write.
     *
     * @return array{Expr, string, bool}|null
     */
    private function writtenMember(Node $node): ?array
    {
        $target = $node instanceof Assign || $node instanceof AssignOp || $node instanceof PreInc
            || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec ? $node->var : null;

        if ($target instanceof PropertyFetch && $target->name instanceof Identifier) {
            return [$target->var, $target->name->toString(), false];
        }

        return $target instanceof ArrayDimFetch && $target->dim instanceof String_
            ? [$target->var, $target->dim->value, true]
            : null;
    }

    /**
     * Whether a write stores in a column a value that column's published type holds on every model it may be written
     * on: a constant, or what `.=`, an arithmetic operator, `++` or `--` leaves.
     *
     * @param  list<class-string>  $models
     */
    private function storesWithinColumn(Node $node, string $member, array $models, AnalysisScope $scope): bool
    {
        $value = match (true) {
            $node instanceof Assign, $node instanceof CoalesceAssign => $this->constantType($node->expr, $scope),
            $node instanceof AssignOp\Concat => 'string',
            $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PostInc, $node instanceof PreDec,
            $node instanceof PostDec => 'number',
            default => null,
        };
        $attributes = resolve(ModelAttributeResolver::class);

        return $value !== null && $models !== [] && array_all($models, fn (string $model): bool => in_array(
            $value,
            TsTypeString::splitTopLevelUnion($attributes->resolveAttribute($model, $member)['type']),
            true,
        ));
    }

    /**
     * The TypeScript type of a constant expression, or null for any other.
     */
    private function constantType(Expr $value, AnalysisScope $scope): ?string
    {
        $values = resolve(ValueResolver::class);

        try {
            return $values->resolveConstantValue($values->evaluateConstantExpression($value, $scope))['type'] ?? null;
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * Whether an expression is the variable, or a chain of method calls on it.
     */
    private function callsOn(Expr $expr, string $name): bool
    {
        while ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === $name;
    }

    /**
     * Whether a nested function shares a variable of the body around it and can run: a closure importing it, or an
     * arrow function with no parameter of its name, neither one that never runs.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $parked
     */
    private function sharesVariable(ArrowFunction|ClosureExpr $function, string $name, array $parked): bool
    {
        $declares = fn (Param $param): bool => $param->var instanceof Variable && $param->var->name === $name;

        return ! in_array($function, $parked, true) && ($function instanceof ClosureExpr
            ? array_any($function->uses, fn (ClosureUse $use): bool => $use->var->name === $name)
            : ! array_any($function->params, $declares));
    }

    /**
     * The closures and arrow functions a body assigns, as a statement of their own, to a variable it spells nowhere
     * else, which nothing can call.
     *
     * @param  array<Node>  $body
     * @return list<ArrowFunction|ClosureExpr>
     */
    private function parkedClosures(array $body): array
    {
        $finder = new NodeFinder;
        $spelled = array_count_values(array_map(
            fn (Variable $variable): string => is_string($variable->name) ? $variable->name : '',
            $finder->findInstanceOf($body, Variable::class),
        ));
        $parked = [];

        foreach ($finder->findInstanceOf($body, ExpressionStmt::class) as $stmt) {
            $assign = $stmt->expr;

            if ($assign instanceof Assign
                && ($assign->expr instanceof ClosureExpr || $assign->expr instanceof ArrowFunction)
                && $assign->var instanceof Variable && is_string($assign->var->name)
                && ($spelled[$assign->var->name] ?? 0) === 1
            ) {
                $parked[] = $assign->expr;
            }
        }

        return $parked;
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
