<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Reads the array a method builds in a local variable and returns, walking the variable's writes from the last whole
 * assignment that replaces it, and the arrays a closure returns as branches. The host analyzer supplies what only it
 * knows through the abstract methods.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ReadsReturnedVariables
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;

    /**
     * The analysis of a whole-array write's value, or null for an expression the host does not read.
     */
    abstract protected function analyzeArrayExpression(Expr $expr, bool $topLevel = true): ?MethodAnalysis;

    /**
     * Whether analyzeArrayExpression() reads an expression, decided without analyzing it.
     */
    abstract protected function readsAsArray(Expr $expr): bool;

    /**
     * The type and optional status of the value a key write assigns.
     *
     * @return ValueExpressionResult
     */
    abstract protected function analyzeValueExpression(Expr $expr): array;

    /**
     * The class under analysis, the subject a warning names.
     *
     * @return ReflectionClass<object>
     */
    abstract protected function subjectReflection(): ReflectionClass;

    /**
     * The string, int or class-constant literal a local holds at a read where the host binds it, so a key write or an
     * unset() through that local names its key; null for any other read.
     */
    abstract protected function literalLocal(Variable $read): ?Expr;

    /**
     * A new, empty analysis of the host's own type, for a variable's walk to fill.
     *
     * @return TAnalysis
     */
    abstract protected function newVariableAnalysis(): MethodAnalysis;

    /**
     * The key and value an append `$var[] = value` writes where the host reads an appended item as a named key; null
     * where an appended item is a position.
     *
     * @return array{name: string, result: ValueExpressionResult}|null
     */
    abstract protected function appendedKey(Expr $value): ?array;

    /**
     * Join the exclusive branches of an `if` chain or a loop, each the keys the variable holds at its end: a key every
     * branch holds keeps each branch's type, and one some branch lacks is optional.
     *
     * @param  non-empty-list<TAnalysis>  $branches
     * @return TAnalysis
     */
    abstract protected function joinedBranches(array $branches): MethodAnalysis;

    /**
     * The analysis of one array literal a closure returns, each key it sets required within that branch.
     *
     * @return TAnalysis
     */
    abstract private function mergedArrayAnalysis(Array_ $array): MethodAnalysis;

    /**
     * The analysis of a merged value other than an array literal or a variable, or null for one the host does not read.
     *
     * @return TAnalysis|null
     */
    abstract private function mergedValueAnalysis(Expr $expr): ?MethodAnalysis;

    /**
     * Resolve what a body returns, a returned variable the scope cannot type read through the walk over its writes.
     *
     * ResolvesReturnedValues entry point for a closure's returns and those of a method read as a value.
     *
     * @param  array<Node\Stmt>  $stmts  the body that returns $returned
     * @return ValueExpressionResult
     */
    public function resolveReturned(Expr $returned, array $stmts): array
    {
        $result = $this->analyzeValueExpression($returned);

        if ($result['type'] !== 'unknown' || ! $returned instanceof Variable || ! is_string($returned->name)) {
            return $result;
        }

        return $this->walkedValue($stmts, $returned->name) ?? $result;
    }

    /**
     * The array a local holds where it is read, walked over the body's statements that end before the read; null where
     * the statement holding the read writes the local too, as a loop can before the read runs again.
     *
     * ResolvesReturnedValues entry point for a value read of a local built by key writes.
     *
     * @param  array<Node\Stmt>  $stmts  the body holding the read
     * @return ValueExpressionResult|null
     */
    public function walkedLocal(Variable $read, array $stmts): ?array
    {
        $name = $read->name;
        $stmts = array_values($stmts);
        $offset = $read->getStartFilePos();
        $before = array_values(array_filter($stmts, fn (Node\Stmt $stmt): bool => $stmt->getEndFilePos() < $offset));
        $holding = $stmts[count($before)] ?? null;

        if (! is_string($name) || $before === [] || ($holding !== null && $this->writesVariable($holding, $name))) {
            return null;
        }

        return $this->walkedValue($before, $name);
    }

    /**
     * Resolve properties from a method that builds an array variable and returns it.
     *
     * Handles: $data = [...]; $data['key'] = expr; if (...) { $data['key'] = expr; } return $data;
     * It reads a variable the gate rejects too, publishing what the walk sees, as an analysis not read completely.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis
     */
    protected function resolveVariableReturnAnalysis(array $stmts, string $varName, bool $topLevel = true): MethodAnalysis
    {
        $reads = $this->readsVariableArray($stmts, $varName);
        $analysis = $this->walkVariable($stmts, $varName, $topLevel);
        $analysis->readsCompletely = $analysis->readsCompletely && $reads;

        return $analysis;
    }

    /**
     * Whether every whole write to a variable has a form the walk reads: each assignment of the variable is an array
     * readsAsArray() accepts, or a `+=` of one, and at least one assigns it. A key write never counts against.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function readsVariableArray(array $stmts, string $varName): bool
    {
        $assigned = false;

        foreach ($this->collectVariableWrites($stmts, $this->subjectReflection()) as [$written, $node, $writtenTo]) {
            if ($written !== $varName) {
                continue;
            }

            // A key unset() is a key write, which the walk judges where it reads the statement.
            $target = $node instanceof Unset_ ? $writtenTo : $this->writeTarget($node);

            if ($target instanceof ArrayDimFetch) {
                continue;
            }

            if (! ($node instanceof Assign || $node instanceof Plus)
                || ! $target instanceof Variable
                || ! $this->readsAsArray($node->expr)) {
                return false;
            }

            $assigned = $assigned || $node instanceof Assign;
        }

        return $assigned;
    }

    /**
     * Recursively collect array assignments to a variable from method statements.
     *
     * The bodies of an `if` chain and of a `foreach`, `for` or `while` join as branches. Assignments inside a `try`
     * body, a `catch`, a `case`, or a `do` body after its first `break` or `continue`, are marked as optional. A
     * whole-array write that analyzes as nothing leaves the variable not read completely, since its keys are unknown.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  TAnalysis  $into
     */
    protected function collectVariableArrayAssignments(
        array $stmts,
        string $varName,
        bool $isConditional,
        MethodAnalysis $into,
        bool $topLevel = true,
    ): void {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof TryCatch || $stmt instanceof Switch_
                || $stmt instanceof Block || $stmt instanceof Do_) {
                $this->collectNestedArrayAssignments($stmt, $varName, $isConditional, $into, $topLevel);

                continue;
            }

            $appendedValue = $this->appendedValue($stmt, $varName);
            $appended = $appendedValue === null ? null : $this->appendedKey($appendedValue);

            if ($appended !== null) {
                $this->writeKey($into, $appended['name'], $appended['result'], $isConditional);

                continue;
            }

            if ($this->writesUnreadKey($stmt, $varName, $into)) {
                $into->readsCompletely = false;

                continue;
            }

            if ($stmt instanceof Unset_) {
                $this->removeUnsetKeys($stmt, $varName, $isConditional, $into);

                continue;
            }

            if (! $stmt instanceof ExpressionStmt && ! $stmt instanceof If_
                && ! $stmt instanceof Foreach_ && ! $stmt instanceof For_ && ! $stmt instanceof While_) {
                continue;
            }

            // $var = [...], or any other whole array analyzeArrayExpression() reads — base array assignment
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName) {
                $baseAnalysis = $this->analyzeArrayExpression($stmt->expr->expr, $topLevel);

                if ($baseAnalysis === null) {
                    $into->readsCompletely = false;

                    continue;
                }

                // A write that always runs replaces every key held before it, as fromLastReplacement() does at the top.
                if (! $isConditional && ! $this->spreadsVariable($stmt->expr->expr, $varName)) {
                    $into->replaceWith($this->newVariableAnalysis());
                }

                $this->mergeWholeArrayWrite($into, $baseAnalysis, $isConditional);

                continue;
            }

            // $var += [...] — PHP's union keeps every key already set, so only the new keys and each signature entry
            // join, and a key the left side keeps drops the right side's channels along with its value.
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Plus
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName) {
                $addedAnalysis = $this->analyzeArrayExpression($stmt->expr->expr, $topLevel);

                if ($addedAnalysis === null) {
                    $into->readsCompletely = false;

                    continue;
                }

                $addedAnalysis->dropKeysHeldBy($into);
                $this->mergeWholeArrayWrite($into, $addedAnalysis, $isConditional);

                continue;
            }

            // $var['key'] = expr — individual key assignment; the key may be a literal string or,
            // for a name built from literal text around a variable, an interpolated index signature.
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof ArrayDimFetch
                && $stmt->expr->var->var instanceof Variable
                && $stmt->expr->var->var->name === $varName
                && $stmt->expr->var->dim !== null
                && ($keyName = $this->namedKey($stmt->expr->var->dim)) !== null) {
                $result = $this->analyzeValueExpression($stmt->expr->expr);

                if (JsEmitter::isIndexSignatureKey($keyName)) {
                    $into->addSignatureEntry($keyName, $result);

                    continue;
                }

                $this->writeKey($into, $keyName, $result, $isConditional);

                continue;
            }

            if ($stmt instanceof If_) {
                $this->collectIfChainAssignments($stmt, $varName, $isConditional, $into, $topLevel);
            }

            // A loop may run zero times, so the keys held before it are a branch beside its body's.
            if ($stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof While_) {
                $this->collectExclusiveAssignments([$stmt->stmts], true, $varName, $isConditional, $into, $topLevel);
            }
        }
    }

    /**
     * The branches a closure's returns can merge, none when no branch sets a key.
     *
     * One per returned array, `[]` included, one per returned variable the walk reads completely, however often it is
     * returned, and one per other value mergedValueBranch() reads, with whether every return was read completely. A
     * variable returned alone is read for the keys its walk sees, as a method's lone returned variable is.
     *
     * @return array{list<TAnalysis>, bool}
     */
    protected function closureReturnBranches(Expr $closure): array
    {
        $stmts = $closure instanceof ClosureExpr ? $closure->stmts : [];
        $returns = $this->resolveClosureReturnExpressions($closure);
        $branches = [];
        $read = [];
        $complete = true;

        foreach ($returns as $returned) {
            if ($returned instanceof Array_) {
                $branches[] = $this->mergedArrayAnalysis($returned);

                continue;
            }

            if ($returned instanceof Variable && is_string($returned->name)) {
                if (isset($read[$returned->name])) {
                    continue;
                }

                $read[$returned->name] = true;
                $branch = count($returns) === 1
                    ? $this->resolveVariableReturnAnalysis($stmts, $returned->name, topLevel: false)
                    : $this->variableBranch($stmts, $returned->name, topLevel: false);
            } else {
                $branch = $this->mergedValueBranch($returned);
            }

            // Its keys are unknown, so a variable holding this merge is not read completely either.
            if ($branch === null) {
                $complete = false;

                continue;
            }

            $branches[] = $branch;
        }

        $complete = $complete && array_all($branches, fn (MethodAnalysis $branch): bool => $branch->readsCompletely);

        // A guard's `return []` merges nothing, so beside a branch that sets a key it is a branch like any other.
        $setsKey = array_any($branches, fn (MethodAnalysis $branch): bool => $branch->properties !== []);

        return [$setsKey ? $branches : [], $complete];
    }

    /**
     * A merged value's branch, or null when the host does not read it completely, as variableBranch() decides.
     *
     * @return TAnalysis|null
     */
    private function mergedValueBranch(Expr $expr): ?MethodAnalysis
    {
        $analysis = $this->mergedValueAnalysis($expr);

        return $analysis !== null && $analysis->readsCompletely ? $analysis : null;
    }

    /**
     * A returned variable's branch, or null when the walk does not read it completely: the gate rejects a whole write,
     * or its walk is not read completely, as over a model-less `parent::toArray()` or an unreadable helper.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis|null
     */
    private function variableBranch(array $stmts, string $varName, bool $topLevel = true): ?MethodAnalysis
    {
        $read = $this->readVariable($stmts, $varName, $topLevel);

        return $read !== null && $read->readsCompletely ? $read : null;
    }

    /**
     * The inline object a variable's walk over a body reads; null where the walk reads it only partly, or reads no key.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return ValueExpressionResult|null
     */
    private function walkedValue(array $stmts, string $varName): ?array
    {
        $branch = $this->variableBranch($stmts, $varName, topLevel: false);

        if ($branch === null || $branch->properties === []) {
            return null;
        }

        resolve(IndexSignatureReconciler::class)->reconcile($branch);

        return new InlineArrayHandler()->inlineObjectResult($branch);
    }

    /**
     * Whether a statement writes the named variable anywhere within it, as collectVariableWrites() counts a write.
     */
    private function writesVariable(Node\Stmt $stmt, string $varName): bool
    {
        return array_any(
            $this->collectVariableWrites([$stmt], $this->subjectReflection()),
            fn (array $write): bool => $write[0] === $varName,
        );
    }

    /**
     * A returned variable's walk, or null when the gate rejects a whole write: a walk not read completely still saw the
     * keys it publishes.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis|null
     */
    private function readVariable(array $stmts, string $varName, bool $topLevel = true): ?MethodAnalysis
    {
        return $this->readsVariableArray($stmts, $varName) ? $this->walkVariable($stmts, $varName, $topLevel) : null;
    }

    /**
     * Walk a variable's writes, from the last whole assignment that replaces it, into a new analysis.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis
     */
    private function walkVariable(array $stmts, string $varName, bool $topLevel): MethodAnalysis
    {
        $analysis = $this->newVariableAnalysis();
        $live = $this->fromLastReplacement($stmts, $varName);

        $this->collectVariableArrayAssignments($live, $varName, false, $analysis, $topLevel);

        return $analysis;
    }

    /**
     * The statements from the last top-level assignment that replaces the whole variable, which overwrites every
     * write before it; an assignment that spreads the variable into itself keeps the earlier keys, so replaces nothing.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return array<Node\Stmt>
     */
    private function fromLastReplacement(array $stmts, string $varName): array
    {
        $stmts = array_values($stmts);
        $start = 0;

        foreach ($stmts as $index => $stmt) {
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName
                && $this->readsAsArray($stmt->expr->expr)
                && ! $this->spreadsVariable($stmt->expr->expr, $varName)) {
                $start = $index;
            }
        }

        return array_slice($stmts, $start);
    }

    /**
     * Whether an array literal spreads the named variable into itself, as `[...$data, 'k' => $v]` does.
     */
    private function spreadsVariable(Expr $expr, string $varName): bool
    {
        return $expr instanceof Array_ && array_any(
            $expr->items,
            fn (ArrayItem $item): bool => $item->unpack
                && $item->value instanceof Variable && $item->value->name === $varName,
        );
    }

    /**
     * Merge a whole-array write into the keys a variable already holds.
     *
     * A new key or a signature entry is appended, optional when the write is conditional or its value can vanish; a
     * named key set again keeps its first position and takes the last value, optional when that value can vanish or a
     * conditional write leaves an optional old one.
     */
    private function mergeWholeArrayWrite(MethodAnalysis $into, MethodAnalysis $write, bool $isConditional): void
    {
        $properties = $into->properties;
        $names = array_column($properties, 'name');
        $written = [];
        $positions = [];

        // A named key the write itself repeats is one key with its last value, as the same literal returned would
        // publish; each signature entry is one more runtime key, kept as addSignatureEntry() keeps one.
        foreach ($write->properties as $prop) {
            if (! JsEmitter::isIndexSignatureKey($prop['name']) && isset($positions[$prop['name']])) {
                $written[$positions[$prop['name']]] = $prop;

                continue;
            }

            $positions[$prop['name']] = count($written);
            $written[] = $prop;
        }

        foreach ($written as $prop) {
            // A signature entry is one more runtime key its signature covers, so it replaces no earlier entry.
            $index = JsEmitter::isIndexSignatureKey($prop['name']) ? false : array_search($prop['name'], $names, true);

            if ($index === false) {
                $properties[] = [...$prop, 'optional' => $isConditional || $prop['optional']];

                continue;
            }

            $into->forgetChannels($prop['name']);
            $optional = $prop['optional'] || ($isConditional && $properties[$index]['optional']);
            $properties[$index] = [...$prop, 'optional' => $optional];
        }

        $into->merge($write);
        $into->properties = $properties;
    }

    /**
     * Collect a variable's writes across an `if` chain, whose bodies exclude each other; with no `else`, none may run.
     *
     * @param  TAnalysis  $into
     */
    private function collectIfChainAssignments(
        If_ $if,
        string $varName,
        bool $isConditional,
        MethodAnalysis $into,
        bool $topLevel,
    ): void {
        $bodies = [$if->stmts, ...array_column($if->elseifs, 'stmts')];

        if ($if->else !== null) {
            $bodies[] = $if->else->stmts;
        }

        $this->collectExclusiveAssignments($bodies, $if->else === null, $varName, $isConditional, $into, $topLevel);
    }

    /**
     * Collect a variable's writes across bodies of which one runs, or none where $mayRunNone. Each body writes into its
     * own copy of the keys held before, which is one more branch where none may run, and the copies join; the copy
     * leads, so earlier types lead each union. A key a body writes takes that body's value there, never another's.
     *
     * @param  non-empty-list<array<Node\Stmt>>  $bodies
     * @param  TAnalysis  $into
     */
    private function collectExclusiveAssignments(
        array $bodies,
        bool $mayRunNone,
        string $varName,
        bool $isConditional,
        MethodAnalysis $into,
        bool $topLevel,
    ): void {
        // Bodies that never name the variable leave its keys as they are.
        $names = fn (Node $node): bool => $node instanceof Variable && $node->name === $varName;

        if (new NodeFinder()->findFirst(array_merge(...$bodies), $names) === null) {
            return;
        }

        $bodies = array_values(array_filter($bodies, fn (array $body): bool => ! $this->exitsElsewhere($body, $varName)));

        if ($bodies === []) {
            return;
        }

        $walked = array_map(function (array $body) use ($varName, $isConditional, $into, $topLevel): MethodAnalysis {
            $branch = clone $into;
            $this->collectVariableArrayAssignments($body, $varName, $isConditional, $branch, $topLevel);

            return $branch;
        }, $bodies);

        $branches = [...($mayRunNone ? [clone $into] : []), ...$walked];

        foreach ($branches as $index => $branch) {
            foreach (array_slice($branches, 0, $index) as $earlier) {
                $branch->dropRepeatsOf($earlier);
            }
        }

        $into->replaceWith($this->joinedBranches($branches));
    }

    /**
     * Whether a body ends in a throw, or in a return of anything but the variable, so its writes never reach the code
     * after it; a body that returns the variable stays, as an early return does.
     *
     * @param  array<Node\Stmt>  $body
     */
    private function exitsElsewhere(array $body, string $varName): bool
    {
        $last = $body === [] ? null : $body[array_key_last($body)];

        return match (true) {
            $last instanceof ExpressionStmt => $last->expr instanceof Throw_,
            $last instanceof Return_ => ! $last->expr instanceof Variable || $last->expr->name !== $varName,
            default => false,
        };
    }

    /**
     * Collect a variable's writes inside a `try`, `switch`, `do` or bare block: a catch or a case may not run, nor a
     * `try` body finish, so their writes are conditional, while a bare block and a `finally` keep the caller's
     * condition. A `do` body, which runs once, keeps it too, until a statement that can `break` or `continue`.
     *
     * @param  TAnalysis  $into
     */
    private function collectNestedArrayAssignments(
        TryCatch|Switch_|Block|Do_ $stmt,
        string $varName,
        bool $isConditional,
        MethodAnalysis $into,
        bool $topLevel,
    ): void {
        if ($stmt instanceof Block) {
            $this->collectVariableArrayAssignments($stmt->stmts, $varName, $isConditional, $into, $topLevel);

            return;
        }

        if ($stmt instanceof Do_) {
            $body = array_values($stmt->stmts);
            $jump = array_find_key($body, $this->holdsBreakOrContinue(...)) ?? count($body);
            $head = array_slice($body, 0, $jump);

            $this->collectVariableArrayAssignments($head, $varName, $isConditional, $into, $topLevel);
            $this->collectVariableArrayAssignments(array_slice($body, $jump), $varName, true, $into, $topLevel);

            return;
        }

        if ($stmt instanceof Switch_) {
            foreach ($stmt->cases as $case) {
                $this->collectVariableArrayAssignments($case->stmts, $varName, true, $into, $topLevel);
            }

            return;
        }

        $this->collectVariableArrayAssignments($stmt->stmts, $varName, true, $into, $topLevel);

        foreach ($stmt->catches as $catch) {
            $this->collectVariableArrayAssignments($catch->stmts, $varName, true, $into, $topLevel);
        }

        if ($stmt->finally !== null) {
            $this->collectVariableArrayAssignments($stmt->finally->stmts, $varName, $isConditional, $into, $topLevel);
        }
    }

    /**
     * Whether a statement holds a `break` or `continue` at any depth, even one a loop or `switch` inside it takes.
     */
    private function holdsBreakOrContinue(Node\Stmt $stmt): bool
    {
        return new NodeFinder()->findFirst(
            $stmt,
            fn (Node $node): bool => $node instanceof Break_ || $node instanceof Continue_,
        ) !== null;
    }

    /**
     * Write one named key into the variable's analysis. A re-assigned key is the last write winning, in the first
     * write's position: every stale channel goes before addProperty() routes this result's own, and the entry it
     * appends is folded back over the earlier one.
     *
     * @param  ValueExpressionResult  $result
     */
    private function writeKey(MethodAnalysis $into, string $keyName, array $result, bool $isConditional): void
    {
        $existingIndex = null;

        foreach ($into->properties as $index => $existing) {
            if ($existing['name'] === $keyName) {
                $existingIndex = $index;

                break;
            }
        }

        $into->forgetChannels($keyName);

        $appendedIndex = count($into->properties);

        $into->addProperty($keyName, $result, $isConditional || $result['optional']);

        // A re-set key is absent where its new value vanishes, or where this write is skipped and the old one was
        // absent.
        if ($existingIndex !== null && isset($into->properties[$appendedIndex])) {
            $appended = $into->properties[$appendedIndex];
            $appended['optional'] = $result['optional']
                || ($isConditional && $into->properties[$existingIndex]['optional']);

            $into->properties[$existingIndex] = $appended;
            array_splice($into->properties, $appendedIndex, 1);
        }
    }

    /**
     * The value a statement `$var[] = value` appends to the variable; null for any other statement.
     */
    private function appendedValue(Node\Stmt $stmt, string $varName): ?Expr
    {
        $appends = $stmt instanceof ExpressionStmt
            && $stmt->expr instanceof Assign
            && $stmt->expr->var instanceof ArrayDimFetch
            && $stmt->expr->var->dim === null
            && $stmt->expr->var->var instanceof Variable
            && $stmt->expr->var->var->name === $varName;

        return $appends ? $stmt->expr->expr : null;
    }

    /**
     * Whether a statement writes the variable through a key the walk does not read: a dynamic, appended or nested key,
     * or a compound write or an increment of a key the walk does not hold. removeUnsetKeys() judges an unset().
     */
    private function writesUnreadKey(Node\Stmt $stmt, string $varName, MethodAnalysis $into): bool
    {
        $write = $stmt instanceof ExpressionStmt ? $stmt->expr : null;
        $target = $write === null ? null : $this->writeTarget($write);

        if (! $target instanceof ArrayDimFetch || $this->keyRoot($target) !== $varName) {
            return false;
        }

        $key = $target->var instanceof Variable && $target->dim !== null ? $this->namedKey($target->dim) : null;

        // The key-write arm reads an assignment to a named key; anything else keeps only a key the walk already holds.
        return $key === null
            || (! $write instanceof Assign && ! in_array($key, array_column($into->properties, 'name'), true));
    }

    /**
     * Remove each named key an unset() takes from the variable, with its import channels and cast, or make it optional
     * where the unset() may not run. An unset() of a key namedUnsetKey() cannot name may take any key, and one of the
     * variable itself takes them all, so either leaves the variable not read completely.
     */
    private function removeUnsetKeys(Unset_ $stmt, string $varName, bool $isConditional, MethodAnalysis $into): void
    {
        foreach ($stmt->vars as $unset) {
            $key = $this->namedUnsetKey($unset, $varName);

            if ($key === null) {
                $into->readsCompletely = $into->readsCompletely && $this->keyRoot($unset) !== $varName;

                continue;
            }

            if ($isConditional) {
                $into->properties = array_map(
                    fn (array $property): array => $property['name'] === $key
                        ? [...$property, 'optional' => true]
                        : $property,
                    $into->properties,
                );

                continue;
            }

            $into->forgetChannels($key);
            $into->properties = array_values(array_filter(
                $into->properties,
                fn (array $property): bool => $property['name'] !== $key,
            ));
        }
    }

    /**
     * The key an unset() takes from the variable itself, named as a key write's is; null for any other unset(), and for
     * a signature key, which stands for many runtime keys.
     */
    private function namedUnsetKey(Expr $unset, string $varName): ?string
    {
        if (! $unset instanceof ArrayDimFetch || $unset->dim === null
            || ! $unset->var instanceof Variable || $unset->var->name !== $varName) {
            return null;
        }

        $key = $this->namedKey($unset->dim);

        return $key === null || JsEmitter::isIndexSignatureKey($key) ? null : $key;
    }

    /**
     * The key a write's dim names, read as resolveKeyName() reads a literal's key, a local holding one included; null
     * for any other.
     */
    private function namedKey(Expr $dim): ?string
    {
        $key = $dim instanceof Variable ? $this->literalLocal($dim) : $dim;

        return $key === null ? null : $this->resolveKeyName($key, $this->subjectReflection());
    }

    /**
     * The name of the variable an expression is, or holds a key of at any depth; null for anything else.
     */
    private function keyRoot(Expr $expr): ?string
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && is_string($expr->name) ? $expr->name : null;
    }

    /**
     * The expression a write node assigns to, or null for a node that is not an assignment, compound or not.
     */
    private function writeTarget(Node $node): ?Expr
    {
        return match (true) {
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof PreInc,
            $node instanceof PostInc, $node instanceof PreDec, $node instanceof PostDec => $node->var,
            default => null,
        };
    }
}
