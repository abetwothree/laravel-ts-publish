<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassMethod;
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
use WeakMap;

/**
 * Reads the array a method builds in a local variable and returns, walking the variable's writes from the last whole
 * assignment that replaces it, and the arrays a closure returns as branches. The host analyzer supplies what only it
 * knows through the abstract methods.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type UntypedKeyWrite = array{string, Expr}
 *
 * @internal
 */
trait ReadsReturnedVariables
{
    use InspectsAstNodes;

    /** @var list<UntypedKeyWrite> each key the walk under way wrote a value the engine cannot type, with that value */
    private array $untypedKeyWrites = [];

    /** @var WeakMap<MethodAnalysis, list<UntypedKeyWrite>>|null each walked or literal branch's untyped key writes */
    private ?WeakMap $branchNotes = null;

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
     * The scope the walk reads in, which an arm its join leaves out is recorded against.
     */
    abstract protected function analysisScope(): AnalysisScope;

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
     * The keys a parameter holds on entry, read from the value the host binds it to; null where it binds none it can
     * read back key by key.
     */
    abstract protected function parameterSeed(string $varName): ?MethodAnalysis;

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
     * Resolve what a body returns, a returned variable the scope cannot type, or a seeded parameter the body writes,
     * read through the walk over its writes.
     *
     * ResolvesReturnedValues entry point for a closure's returns and those of a method read as a value.
     *
     * @param  array<Node\Stmt>  $stmts  the body that returns $returned
     * @return ValueExpressionResult
     */
    public function resolveReturned(Expr $returned, array $stmts): array
    {
        $result = $this->analyzeValueExpression($returned);

        if (! $returned instanceof Variable || ! is_string($returned->name)) {
            return $result;
        }

        $bound = $this->parameterSeed($returned->name);

        // A parameter's binding is what it held on entry, which misses each write the body makes to it after.
        $walks = $result['type'] === 'unknown' || ($bound !== null && $this->writesVariable($stmts, $returned->name));

        return $walks ? $this->walkedValue($stmts, $returned->name, $bound) ?? $result : $result;
    }

    /**
     * The array a local holds where it is read, walked over the body's statements that end before the read. Null for a
     * variable variable, where none does, where the one holding the read writes the local too, as a loop can before the
     * read runs again, or where the walk reads the writes only partly or finds no key.
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

        if (! is_string($name) || $before === [] || ($holding !== null && $this->writesVariable([$holding], $name))) {
            return null;
        }

        return $this->walkedValue($before, $name, $this->parameterSeed($name));
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
        $seed = $this->entrySeed($stmts, $varName, $this->parameterSeed($varName));
        $reads = $this->readsVariableArray($stmts, $varName, $seed);
        $analysis = $this->walkVariable($stmts, $varName, $topLevel, $seed);
        $analysis->readsCompletely = $analysis->readsCompletely && $reads;

        return $analysis;
    }

    /**
     * Whether every whole write to a variable has a form the walk reads: each assignment of the variable is an array
     * readsAsArray() accepts, or a `+=` of one, and at least one assigns it or the walk starts from $seed. A key write
     * never counts against.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function readsVariableArray(array $stmts, string $varName, ?MethodAnalysis $seed): bool
    {
        $assigned = false;

        foreach (VariableWrites::of($stmts, $this->subjectReflection()) as [$written, $node, $writtenTo]) {
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

        return $assigned || $seed !== null;
    }

    /**
     * Whether a method returns a parameter its body writes only by key and the walk starts with no seed, so the keys
     * its caller passed reach the return unread.
     */
    protected function returnsUnseededParameter(ClassMethod $method): bool
    {
        if ($method->params === []) {
            return false;
        }

        $stmts = $method->stmts ?? [];
        $parameters = [];
        $returned = [];

        foreach ($method->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $parameters[] = $param->var->name;
            }
        }

        foreach ($this->collectReturnExpressions($stmts) as $expr) {
            if ($expr instanceof Variable && is_string($expr->name) && in_array($expr->name, $parameters, true)) {
                $returned[$expr->name] = false;
            }
        }

        $writes = $returned === []
            ? []
            : VariableWrites::of($stmts, $this->subjectReflection(), $this->analysisScope()->declaringFileClass);

        foreach ($writes as [$name, , $target]) {
            if (! isset($returned[$name])) {
                continue;
            }

            if (! $target instanceof ArrayDimFetch) {
                // A whole write, a `foreach` target or a by-reference argument may replace what the caller passed.
                unset($returned[$name]);
            } elseif (VariableWrites::rootVariable($target) === $name) {
                $returned[$name] = true;
            }
        }

        // Written only by key, a parameter starts from its host seed itself, as entrySeed() would start it.
        return array_any(
            array_keys(array_filter($returned)),
            fn (string $name): bool => $this->parameterSeed($name) === null,
        );
    }

    /**
     * Recursively collect array assignments to a variable from method statements.
     *
     * The bodies of an `if` chain and of a `foreach`, `for` or `while` join as branches. Assignments inside a `try`
     * body, a `catch`, a `case`, or a `do` body after its first `break` or `continue`, are marked as optional. A
     * whole-array write that analyzes as nothing, or a key write the walk cannot name, leaves unread keys.
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
                $this->noteUntypedWrite($appended['name'], $appendedValue, $appended['result']);
                $this->writeKey($into, $appended['name'], $appended['result'], $isConditional);

                continue;
            }

            if ($this->writesUnreadKey($stmt, $varName, $into)) {
                $into->holdUnreadKeys();

                continue;
            }

            // The statement's own writes still land beside what the append adds.
            if ($this->appendsByReference($stmt, $varName)) {
                $into->holdUnreadKeys();
            }

            if ($stmt instanceof Unset_) {
                $this->removeUnsetKeys($stmt, $varName, $isConditional, $into);

                continue;
            }

            if (! $stmt instanceof ExpressionStmt && ! $stmt instanceof If_
                && ! $stmt instanceof Foreach_ && ! $stmt instanceof For_ && ! $stmt instanceof While_) {
                continue;
            }

            $assigned = $this->assignedValue($stmt, $varName);

            // $var = [...], or any other whole array analyzeArrayExpression() reads — base array assignment
            if ($assigned !== null) {
                $baseAnalysis = $this->analyzeArrayExpression($assigned, $topLevel);

                if ($baseAnalysis === null) {
                    $into->holdUnreadKeys();

                    continue;
                }

                // A write that always runs replaces every key held before it, as fromLastReplacement() does at the top.
                if (! $isConditional && ! $this->spreadsVariable($assigned, $varName)) {
                    $into->replaceWith($this->newVariableAnalysis());
                }

                $this->noteUntypedItems($assigned, $baseAnalysis);
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
                    $into->holdUnreadKeys();

                    continue;
                }

                $addedAnalysis->dropKeysHeldBy($into);
                $this->noteUntypedItems($stmt->expr->expr, $addedAnalysis);
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
                $this->noteUntypedWrite($keyName, $stmt->expr->expr, $result);

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
     * One per returned array, `[]` included, per returned variable the walk reads completely, however often returned,
     * or returned alone with no unset() it cannot name, as a method's lone one is read, and per other value
     * mergedValueBranch() reads, with whether every return was read completely.
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
                $branches[] = $this->literalBranch($returned, $this->mergedArrayAnalysis($returned));

                continue;
            }

            if ($returned instanceof Variable && is_string($returned->name)) {
                if (isset($read[$returned->name])) {
                    continue;
                }

                $read[$returned->name] = true;
                $branch = count($returns) === 1 && ! $this->unsetsUnnamedKey($stmts, $returned->name)
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
     * An array literal's analysis as a merge call's or a spread helper's branch, beside the items it types `unknown`.
     *
     * @param  TAnalysis  $branch
     * @return TAnalysis
     */
    protected function literalBranch(Array_ $literal, MethodAnalysis $branch): MethodAnalysis
    {
        $this->branchNotes ??= new WeakMap;
        $this->branchNotes[$branch] = $this->untypedItems($literal, $branch);

        return $branch;
    }

    /**
     * A merge call's or a spread helper's join, once each value it left out beside a typed one is recorded and warned
     * of, as the walk's join does, naming the untyped write its walked or literal branch saw.
     *
     * @param  TAnalysis  $joined
     * @param  list<TAnalysis>  $branches
     * @return TAnalysis
     */
    protected function recordedMergeJoin(MethodAnalysis $joined, array $branches): MethodAnalysis
    {
        $notes = array_map(fn (MethodAnalysis $branch): array => $this->branchNotes[$branch] ?? [], $branches);
        $record = fn (Expr $write) => DroppedUnionArms::record($write, $this->analysisScope(), 'merge-branch-join');
        $this->recordJoinDrops($joined, $branches, $notes, $record);
        // A join can be a branch of another, as a merged spread helper's is, which names what its branches saw.
        $this->branchNotes ??= new WeakMap;
        $this->branchNotes[$joined] = array_merge(...$notes);

        return $joined;
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
     * The inline object a variable's walk over a body reads, from the keys $bound gives a parameter on entry; null
     * where the walk reads it only partly, or reads no key.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return ValueExpressionResult|null
     */
    private function walkedValue(array $stmts, string $varName, ?MethodAnalysis $bound): ?array
    {
        $branch = $this->readFrom($stmts, $varName, false, $bound);

        if ($branch === null || ! $branch->readsCompletely || $branch->properties === []) {
            return null;
        }

        resolve(IndexSignatureReconciler::class)->reconcile($branch);

        return new InlineObjectResult()->of($branch);
    }

    /**
     * Whether statements write the named variable anywhere within them, as VariableWrites::of() counts a write.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function writesVariable(array $stmts, string $varName): bool
    {
        return array_any(
            VariableWrites::of($stmts, $this->subjectReflection()),
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
        return $this->readFrom($stmts, $varName, $topLevel, $this->parameterSeed($varName));
    }

    /**
     * A returned variable's walk from the keys $bound gives a parameter on entry, or null when the gate rejects a whole
     * write; the gate and the walk share the one seed.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis|null
     */
    private function readFrom(array $stmts, string $varName, bool $topLevel, ?MethodAnalysis $bound): ?MethodAnalysis
    {
        $seed = $this->entrySeed($stmts, $varName, $bound);

        return $this->readsVariableArray($stmts, $varName, $seed)
            ? $this->walkVariable($stmts, $varName, $topLevel, $seed)
            : null;
    }

    /**
     * Walk a variable's writes, from the last whole assignment that replaces it, into a new analysis; with none at the
     * top level, from $seed, the keys a parameter holds on entry.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis
     */
    private function walkVariable(array $stmts, string $varName, bool $topLevel, ?MethodAnalysis $seed): MethodAnalysis
    {
        $analysis = $this->newVariableAnalysis();

        if ($seed !== null) {
            $analysis->merge($seed);
        }

        $live = $this->fromLastReplacement($stmts, $varName);
        // A value read during the walk can start a walk of its own, whose writes are not this one's.
        $outer = $this->untypedKeyWrites;
        $this->untypedKeyWrites = [];

        try {
            $this->collectVariableArrayAssignments($live, $varName, false, $analysis, $topLevel);
            $this->branchNotes ??= new WeakMap;
            $this->branchNotes[$analysis] = $this->untypedKeyWrites;
        } finally {
            $this->untypedKeyWrites = $outer;
        }

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
            $value = $this->replacingValue($stmt, $varName);

            if ($value !== null && $this->readsAsArray($value)) {
                $start = $index;
            }
        }

        return array_slice($stmts, $start);
    }

    /**
     * The keys a parameter whose host seed is $bound holds where the walk over a body starts; null where a top-level
     * write replaces it, an assignment whether the walk reads its value or not, or the host seeds it with none.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function entrySeed(array $stmts, string $varName, ?MethodAnalysis $bound): ?MethodAnalysis
    {
        // Only a seed pays for the write scan, which reflects the callee of each call passed the variable.
        if ($bound === null
            || array_any($stmts, fn (Node\Stmt $stmt): bool => $this->replacingValue($stmt, $varName) !== null)) {
            return null;
        }

        return $this->rewritesWhole($stmts, $varName) ? null : $bound;
    }

    /**
     * Whether a top-level statement writes the whole variable other than by its own `=`, `+=` or `??=`, as an embedded
     * assignment, destructuring, a by-reference argument, a `foreach` target or unset() does, so the walk cannot tell
     * which keys it still holds. A write inside a block the statement holds may not run.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function rewritesWhole(array $stmts, string $varName): bool
    {
        foreach ($stmts as $stmt) {
            $blocks = array_merge(...StatementBlocks::inner($stmt));
            $own = $stmt instanceof ExpressionStmt ? $stmt->expr : null;

            foreach (VariableWrites::of([$stmt], $this->subjectReflection()) as [$written, $node, $target]) {
                // A seed binds a value that is never null, so its own `??=` never runs.
                $assigns = $node === $own && $target instanceof Variable
                    && ($node instanceof Assign || $node instanceof Plus || $node instanceof CoalesceAssign);

                if ($written !== $varName || $target instanceof ArrayDimFetch || $assigns
                    || array_any($blocks, fn (Node\Stmt $inner): bool => StatementBlocks::encloses($inner, $node))) {
                    continue;
                }

                if (! $node instanceof CallLike || ! resolve(ByReferenceArguments::class)->keepsStringKeys($node, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a statement's expression passes the variable to a by-reference call that adds values to it under keys the
     * walk never names, array_push(), array_unshift() or array_splice(), as the statement or inside it.
     */
    private function appendsByReference(Node\Stmt $stmt, string $varName): bool
    {
        return $stmt instanceof ExpressionStmt && $this->bodyHas(
            [$stmt],
            fn (Node $node): bool => $node instanceof CallLike && ! $node->isFirstClassCallable() && array_any(
                $node->getArgs(),
                fn (Arg $arg): bool => $arg->value instanceof Variable && $arg->value->name === $varName
                    && resolve(ByReferenceArguments::class)->effect($node, $arg->value) === ByReferenceEffect::Appends,
            ),
        );
    }

    /**
     * The value a statement `$var = value;` assigns where it replaces every key the variable held, one that spreads
     * the variable into itself aside; null for any other statement.
     */
    private function replacingValue(Node\Stmt $stmt, string $varName): ?Expr
    {
        $value = $this->assignedValue($stmt, $varName);

        return $value !== null && ! $this->spreadsVariable($value, $varName) ? $value : null;
    }

    /**
     * The value a statement assigns the whole variable, as `$var = value;` or a chain such as `$copy = $var = value;`
     * does; null for any other statement.
     */
    private function assignedValue(Node\Stmt $stmt, string $varName): ?Expr
    {
        $expr = $stmt instanceof ExpressionStmt ? $stmt->expr : null;
        $assigns = false;

        while ($expr instanceof Assign) {
            $assigns = $assigns || ($expr->var instanceof Variable && $expr->var->name === $varName);
            $expr = $expr->expr;
        }

        return $assigns ? $expr : null;
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

        $before = $this->untypedKeyWrites;
        $branches = $mayRunNone ? [clone $into] : [];
        // Each branch's untyped writes: those before the branches, then its own, never a sibling's.
        $notes = $mayRunNone ? [$before] : [];
        $added = [];

        foreach ($bodies as $body) {
            $this->untypedKeyWrites = $before;
            $branch = clone $into;
            $this->collectVariableArrayAssignments($body, $varName, $isConditional, $branch, $topLevel);
            $branches[] = $branch;
            $notes[] = $this->untypedKeyWrites;
            array_push($added, ...array_slice($this->untypedKeyWrites, count($before)));
        }

        $this->untypedKeyWrites = [...$before, ...$added];

        foreach ($branches as $index => $branch) {
            foreach (array_slice($branches, 0, $index) as $earlier) {
                $branch->dropRepeatsOf($earlier);
            }
        }

        $joined = $this->joinedBranches($branches);
        $record = fn (Expr $write) => DroppedUnionArms::record($write, $this->analysisScope(), 'walk-branch-join');
        $this->recordJoinDrops($joined, $branches, $notes, $record);
        $into->replaceWith($joined);
    }

    /**
     * Record each branch value a join left out beside a typed one, naming the last untyped write of its key the branch
     * saw, and warn of each: it still reaches the read.
     *
     * @param  list<TAnalysis>  $branches
     * @param  list<list<UntypedKeyWrite>>  $notes  the untyped writes each branch saw
     * @param  Closure(Expr): void  $record  records one value left out, under its join's site
     */
    private function recordJoinDrops(MethodAnalysis $joined, array $branches, array $notes, Closure $record): void
    {
        if (array_merge(...$notes) === []) {
            return;
        }

        $types = array_map(
            fn (MethodAnalysis $branch): array => array_column($branch->properties, 'type', 'name'),
            $branches,
        );

        foreach ($joined->properties as $property) {
            if ($property['type'] === 'unknown') {
                continue;
            }

            $dropped = [];

            foreach ($types as $index => $branchTypes) {
                $write = ($branchTypes[$property['name']] ?? null) === 'unknown'
                    ? $this->lastUntypedWrite($property['name'], $notes[$index])
                    : null;

                if ($write !== null) {
                    $record($write);
                    $dropped[] = $write;
                }
            }

            if ($dropped !== []) {
                $union = [...ValueResult::unknown(), 'type' => $property['type']];
                DroppedUnionArms::warnOfJoinDrop($union, $dropped, $this->analysisScope());
            }
        }
    }

    /**
     * The last value written to a key that the engine cannot type, among the untyped writes one branch saw.
     *
     * @param  list<UntypedKeyWrite>  $notes
     */
    private function lastUntypedWrite(string $key, array $notes): ?Expr
    {
        for ($index = count($notes) - 1; $index >= 0; $index--) {
            if ($notes[$index][0] === $key) {
                return $notes[$index][1];
            }
        }

        return null;
    }

    /**
     * Note each item of an array literal written whole whose key the write's analysis types `unknown`.
     */
    private function noteUntypedItems(Expr $array, MethodAnalysis $write): void
    {
        array_push($this->untypedKeyWrites, ...$this->untypedItems($array, $write));
    }

    /**
     * Each item of an array literal whose key its analysis types `unknown`, with its value; none for another value.
     *
     * @return list<UntypedKeyWrite>
     */
    private function untypedItems(Expr $array, MethodAnalysis $analysis): array
    {
        if (! $array instanceof Array_) {
            return [];
        }

        $types = array_column($analysis->properties, 'type', 'name');
        $untyped = [];

        foreach ($array->items as $item) {
            $key = $item->key === null || $item->unpack ? null : $this->namedKey($item->key);

            if ($key !== null && ($types[$key] ?? null) === 'unknown') {
                $untyped[] = [$key, $item->value];
            }
        }

        return $untyped;
    }

    /**
     * Note a key write whose value the engine cannot type, which a join may leave out beside a typed branch.
     *
     * @param  ValueExpressionResult  $result
     */
    private function noteUntypedWrite(string $key, Expr $value, array $result): void
    {
        if ($result['type'] === 'unknown') {
            $this->untypedKeyWrites[] = [$key, $value];
        }
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
     * Whether an unset() past the variable's last whole replacement takes from it a key the walk cannot name, which may
     * be any key the walk reads.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function unsetsUnnamedKey(array $stmts, string $varName): bool
    {
        $unsets = new NodeFinder()->findInstanceOf($this->fromLastReplacement($stmts, $varName), Unset_::class);

        foreach ($unsets as $unset) {
            foreach ($unset->vars as $var) {
                if ($this->keyRoot($var) === $varName && $this->namedUnsetKey($var, $varName) === null) {
                    return true;
                }
            }
        }

        return false;
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
