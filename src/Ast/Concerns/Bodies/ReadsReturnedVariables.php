<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Bodies;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\VariableWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build\ArrayWalk;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaqueKind;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\OpaquePart;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceEffect;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassLike;
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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use WeakMap;

/**
 * Reads the array a method builds in a local variable and returns, walking the variable's writes from the last whole
 * assignment that replaces it, and the arrays a closure returns as branches. The host analyzer supplies what only it
 * knows through the abstract methods.
 *
 * @template TAnalysis of MethodAnalysis
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type LeftOutArm from DroppedUnionArms
 * @phpstan-import-type UntypedKeyWrite from ArrayWalk
 *
 * @internal
 */
trait ReadsReturnedVariables
{
    use InspectsAstNodes;

    /** The built-ins whose result keeps every string key of the array they take first. */
    private const array KEEPS_FIRST_KEYS = [
        'array_merge',
        'array_merge_recursive',
        'array_replace',
        'array_replace_recursive',
    ];

    /** @var WeakMap<MethodAnalysis, list<UntypedKeyWrite>>|null each walked or literal branch's untyped key writes */
    private ?WeakMap $branchNotes = null;

    /** How many values written to a variable read it where the walk could not spell its keys, which a page declines. */
    private int $selfReadsMissed = 0;

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
     * The scope the walk reads in, which an arm its join leaves out is recorded against.
     */
    abstract protected function analysisScope(): AnalysisScope;

    /**
     * How the values the walk reads reach the client, which decides what a key's value sends.
     */
    abstract public function reading(): Reading;

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

        if (! is_string($name)) {
            return null;
        }

        $before = $this->statementsBefore($read, $name, $stmts);

        return $before === null ? null : $this->walkedValue($before, $name, $this->parameterSeed($name));
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
     * The keys a local holds where it is read, walked as a returned variable's are; null where the walk declines.
     *
     * A function or class nested in the body may bind the name itself, so a read inside one is declined too, and so is
     * one past a write whose value the walk may lose (writesPastTheWalk()), or past a value written to the variable
     * that the walk could not read against its keys.
     *
     * @param  array<Node\Stmt>  $stmts  the body holding the read
     * @return TAnalysis|null
     */
    protected function walkedAnalysis(Variable $read, array $stmts): ?MethodAnalysis
    {
        $name = $read->name;
        $nested = fn (Node $node): bool => ($node instanceof FunctionLike || $node instanceof ClassLike)
            && StatementBlocks::encloses($node, $read);

        if (! is_string($name) || new NodeFinder()->findFirst($stmts, $nested) !== null) {
            return null;
        }

        $before = $this->statementsBefore($read, $name, $stmts);

        if ($before === null || $this->writesPastTheWalk($before, $name)) {
            return null;
        }

        $missed = $this->selfReadsMissed;
        $branch = $this->variableBranch($before, $name);

        // Such a value is read as the body's other reads read it, which can leave out the type of a key that is sent.
        return $this->selfReadsMissed === $missed ? $branch : null;
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

        foreach (VariableWrites::of($stmts, $this->analysisScope()) as [$written, $node, $writtenTo]) {
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
            : VariableWrites::of($stmts, $this->analysisScope());

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
     * @param  bool  $nested  whether the statements sit in a block the walk's top level holds, where a whole write
     *                        the walk cannot read may not run, so leaves each key held before it optional
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    protected function collectVariableArrayAssignments(
        array $stmts,
        string $varName,
        bool $isConditional,
        ArrayWalk $walk,
        bool $topLevel = true,
        bool $nested = false,
    ): void {
        foreach ($stmts as $stmt) {
            $rewrites = $nested && $this->rewritesWhole([$stmt], $varName);

            if ($stmt instanceof TryCatch || $stmt instanceof Switch_
                || $stmt instanceof Block || $stmt instanceof Do_) {
                $this->collectNestedArrayAssignments($stmt, $varName, $isConditional, $walk, $topLevel);

                // Its own write, a `do` condition or a `catch` variable, lands after a block it holds has run.
                if ($rewrites) {
                    $walk->loosen(new OpaquePart($stmt, OpaqueKind::UnreadWholeWrite));
                }

                continue;
            }

            // An unset() of the whole variable brings no key.
            if ($rewrites) {
                $walk->loosen($stmt instanceof Unset_ ? null : new OpaquePart($stmt, OpaqueKind::UnreadWholeWrite));
            }

            $appendedValue = $this->appendedValue($stmt, $varName);
            $appended = $appendedValue === null ? null : $this->appendedKey($appendedValue);

            // The name an append writes is the key PHP stores, so it never reads as a signature.
            if ($appended !== null) {
                $walk->writeName($appended['name'], $appended['result'], $isConditional, $appendedValue);

                continue;
            }

            if ($this->writesUnreadKey($stmt, $varName, $walk)) {
                $walk->holdOpaque(new OpaquePart($stmt, OpaqueKind::UnnamedKeyWrite));

                continue;
            }

            // The statement's own writes still land beside what the append adds.
            if ($this->appendsByReference($stmt, $varName)) {
                $walk->holdOpaque(new OpaquePart($stmt, OpaqueKind::ByReferenceAppend));
            }

            if ($stmt instanceof Unset_) {
                $this->removeUnsetKeys($stmt, $varName, $isConditional, $walk);

                continue;
            }

            if (! $stmt instanceof ExpressionStmt && ! $stmt instanceof If_
                && ! $stmt instanceof Foreach_ && ! $stmt instanceof For_ && ! $stmt instanceof While_) {
                continue;
            }

            $assigned = $this->assignedValue($stmt, $varName);

            // $var = [...], or any other whole array analyzeArrayExpression() reads — base array assignment; a whole
            // write, as a `+=` below, lands its keys at the level of the walk.
            if ($assigned !== null) {
                $baseAnalysis = $this->analysisScope()->readingLevel(fn (): ?MethodAnalysis => $this->readAgainstWalk(
                    $assigned,
                    $varName,
                    $walk->state(...),
                    fn (Expr $value): ?MethodAnalysis => $this->analyzeArrayExpression($value, $topLevel),
                ));

                if ($baseAnalysis === null) {
                    $keepsKeys = $stmt instanceof ExpressionStmt && $this->keepsEveryKey($stmt->expr, $varName);

                    if ($nested && ! $keepsKeys) {
                        $walk->loosen(new OpaquePart($stmt, OpaqueKind::UnreadWholeWrite));
                    } else {
                        $walk->holdOpaque(new OpaquePart($stmt, OpaqueKind::UnreadWholeWrite));
                    }

                    continue;
                }

                // A write that always runs replaces every key held before it, as fromLastReplacement() does at the top.
                $walk->assign(ArrayShapes::ofAnalysis($baseAnalysis), $isConditional, $assigned);

                continue;
            }

            // $var += [...] — PHP's union keeps every key already set, so the right side lands only on a key some path
            // left unset, a new key or a signature entry.
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Plus
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName) {
                $added = $stmt->expr->expr;
                $addedAnalysis = $this->analysisScope()->readingLevel(fn (): ?MethodAnalysis => $this->readAgainstWalk(
                    $added,
                    $varName,
                    $walk->state(...),
                    fn (Expr $value): ?MethodAnalysis => $this->analyzeArrayExpression($value, $topLevel),
                ));

                if ($addedAnalysis === null) {
                    $walk->holdOpaque(new OpaquePart($stmt, OpaqueKind::UnreadWholeWrite));

                    continue;
                }

                $walk->plus(ArrayShapes::ofAnalysis($addedAnalysis), $isConditional, $added);

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
                $written = $stmt->expr->expr;
                $result = $this->analysisScope()->readingKey(
                    $keyName,
                    fn (): array => $this->readAgainstWalk($written, $varName, $walk->state(...), $this->analyzeValueExpression(...)),
                );
                $walk->write($keyName, $result, $isConditional, $written);

                continue;
            }

            // $var['key'] ??= expr on a key the walk holds; writesUnreadKey() left one it does not hold unread.
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof CoalesceAssign
                && $stmt->expr->var instanceof ArrayDimFetch
                && $stmt->expr->var->var instanceof Variable
                && $stmt->expr->var->var->name === $varName
                && $stmt->expr->var->dim !== null
                && ($keyName = $this->namedKey($stmt->expr->var->dim)) !== null) {
                $default = $stmt->expr->expr;
                $walk->coalesce($keyName, fn (MethodAnalysis $state): array => $this->analysisScope()->readingKey(
                    $keyName,
                    fn (): array => $this->readAgainstWalk(
                        $default,
                        $varName,
                        static fn (): MethodAnalysis => $state,
                        $this->analyzeValueExpression(...),
                    ),
                ), $isConditional);

                continue;
            }

            if ($stmt instanceof If_) {
                $this->collectIfChainAssignments($stmt, $varName, $isConditional, $walk, $topLevel);
            }

            // A loop may run zero times, so the keys held before it are a branch beside its body's.
            if ($stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof While_) {
                $this->collectExclusiveAssignments([$stmt->stmts], true, $varName, $isConditional, $walk, $topLevel);
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
        $this->branchNotes[$branch] = ArrayWalk::untypedItems($literal, $branch, $this->namedKey(...));

        return $branch;
    }

    /**
     * A merge call's or a spread helper's join, once each value it left out beside a typed one is recorded and warned
     * of, as the walk's join does, naming the untyped write its walked or literal branch saw.
     *
     * @param  TAnalysis  $joined
     * @param  list<TAnalysis>  $branches
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     * @return TAnalysis
     */
    protected function recordedMergeJoin(MethodAnalysis $joined, array $branches, array $returns = []): MethodAnalysis
    {
        $notes = array_map(fn (MethodAnalysis $branch): array => $this->branchNotes[$branch] ?? [], $branches);
        $this->recordJoinDrops($joined, $branches, $notes, fn (Expr $write): array => [$write, 'merge-branch-join'], $returns);
        // A join can be a branch of another, as a merged spread helper's is, which names what its branches saw.
        $this->branchNotes ??= new WeakMap;
        $this->branchNotes[$joined] = array_merge(...$notes);

        return $joined;
    }

    /**
     * The keys a parameter holds on entry: the object literal its bound value spells, read back key by key; null where
     * the host binds none.
     */
    protected function parameterSeed(string $varName): ?MethodAnalysis
    {
        $scope = $this->analysisScope();
        $bound = $scope->varValueBindings[$varName] ?? null;

        return $bound === null ? null : ObjectMembers::seed($bound, $scope->carriesImports);
    }

    /**
     * The string, int or class-constant literal a local holds at a read that sees its one write, so a key write or an
     * unset() through that local names its key; null for any other read.
     */
    protected function literalLocal(Variable $read): ?Expr
    {
        $bound = $this->soleSpelledWrite($read, $this->analysisScope());

        return $bound instanceof String_ || $bound instanceof Int_ || $bound instanceof ClassConstFetch ? $bound : null;
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
     * The statements ending before a read, or null where none does or the one holding the read writes the local too.
     *
     * @param  array<Node\Stmt>  $stmts  the body holding the read
     * @return list<Node\Stmt>|null
     */
    private function statementsBefore(Variable $read, string $varName, array $stmts): ?array
    {
        $stmts = array_values($stmts);
        $offset = $read->getStartFilePos();
        $before = array_values(array_filter($stmts, fn (Node\Stmt $stmt): bool => $stmt->getEndFilePos() < $offset));
        $holding = $stmts[count($before)] ?? null;

        return $before === [] || ($holding !== null && $this->writesVariable([$holding], $varName)) ? null : $before;
    }

    /**
     * Whether statements write the variable where the walk keeps only one of the values it may hold.
     *
     * Those are a `+=` of it, which drops the right side's copy of a key the left may lack, a loop writing it that a
     * `continue` or `break` leaves early, and a `try` or `switch` writing it, whose writes it reads in sequence.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function writesPastTheWalk(array $stmts, string $varName): bool
    {
        $loses = function (Node $node) use ($varName): bool {
            $loop = $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_ || $node instanceof Do_;

            return match (true) {
                $node instanceof Plus => $node->var instanceof Variable && $node->var->name === $varName,
                $node instanceof TryCatch, $node instanceof Switch_ => $this->writesVariable([$node], $varName),
                $loop => $this->holdsBreakOrContinue($node) && $this->writesVariable([$node], $varName),
                default => false,
            };
        };

        return new NodeFinder()->findFirst($stmts, $loses) !== null;
    }

    /**
     * Whether statements write the named variable anywhere within them, as VariableWrites::of() counts a write.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function writesVariable(array $stmts, string $varName): bool
    {
        return array_any(
            VariableWrites::of($stmts, $this->analysisScope()),
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
        $walk = ArrayWalk::from(
            $seed === null ? null : ArrayShapes::ofAnalysis($seed),
            $varName,
            $this->newVariableAnalysis(...),
            fn (array $result): array => $this->reading()->arrayValue($result),
            $this->namedKey(...),
        );
        $live = $this->fromLastReplacement($stmts, $varName, readsBefore: true);
        $collect = fn () => $this->collectVariableArrayAssignments($live, $varName, false, $walk, $topLevel);

        // A typed key written from the variable reads the keys written before it, so each reads as that key does.
        $this->typedWriteReadsVariable($live, $varName) ? $this->analysisScope()->readingLevel($collect) : $collect();
        $analysis = $walk->state();
        $this->branchNotes ??= new WeakMap;
        $this->branchNotes[$analysis] = $walk->untypedWrites();
        resolve(ShapeObserver::class)->observed($analysis);

        return $analysis;
    }

    /**
     * The statements from the last top-level assignment that replaces the whole variable, which overwrites every
     * write before it; an assignment that spreads the variable into itself keeps the earlier keys, so replaces nothing.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  bool  $readsBefore  whether one whose value reads the variable needs the keys written before it, as the
     *                             walk reading that value does
     * @return array<Node\Stmt>
     */
    private function fromLastReplacement(array $stmts, string $varName, bool $readsBefore = false): array
    {
        $stmts = array_values($stmts);
        $start = 0;

        foreach ($stmts as $index => $stmt) {
            $value = $this->replacingValue($stmt, $varName);

            if ($value !== null && $this->readsAsArray($value)
                && ! ($readsBefore && $this->readsVariableIn($value, $varName))) {
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

        return $this->rewritesWhole($stmts, $varName, inPlaceToo: true) ? null : $bound;
    }

    /**
     * Whether a statement writes the whole variable other than by its own `=`, `+=` or `??=`, as an embedded
     * assignment, destructuring, a by-reference argument, a `foreach` target or unset() does, so the walk cannot tell
     * which keys it still holds. A write inside a block the statement holds may not run, so the walk judges it there.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  bool  $inPlaceToo  whether a rewrite of the values in place counts too, inside a block as well, as it
     *                            does for a seed, whose old value types the walk would keep
     */
    private function rewritesWhole(array $stmts, string $varName, bool $inPlaceToo = false): bool
    {
        foreach ($stmts as $stmt) {
            $blocks = array_merge(...StatementBlocks::inner($stmt));
            $own = $stmt instanceof ExpressionStmt ? $stmt->expr : null;

            foreach (VariableWrites::of([$stmt], $this->analysisScope()) as [$written, $node, $target]) {
                // A seed binds a value that is never null, so its own `??=` never runs.
                $assigns = $node === $own && $target instanceof Variable
                    && ($node instanceof Assign || $node instanceof Plus || $node instanceof CoalesceAssign);

                if ($written !== $varName || $target instanceof ArrayDimFetch || $assigns) {
                    continue;
                }

                if ($inPlaceToo && $this->rewritesValuesInPlace($node, $varName)) {
                    return true;
                }

                if (! array_any($blocks, fn (Node\Stmt $inner): bool => StatementBlocks::encloses($inner, $node))
                    && ! $this->keepsEveryKey($node, $varName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a statement's expression passes the variable to a by-reference call that adds values to it under keys the
     * walk never names, array_push(), array_unshift(), array_splice() or `Arr::set()`, as the statement or inside it.
     */
    private function appendsByReference(Node\Stmt $stmt, string $varName): bool
    {
        return $stmt instanceof ExpressionStmt && $this->bodyHas(
            [$stmt],
            fn (Node $node): bool => $node instanceof CallLike && ! $node->isFirstClassCallable() && (
                $this->callsArrOn($node, 'set', $varName) || array_any(
                    $node->getArgs(),
                    fn (Arg $arg): bool => $arg->value instanceof Variable && $arg->value->name === $varName
                        && resolve(ByReferenceArguments::class)->effect($node, $arg->value)
                            === ByReferenceEffect::Appends,
                )
            ),
        );
    }

    /**
     * Whether a whole write keeps every string key the variable holds: an `=` of a merge or a replace taking it first,
     * of `Arr::add()` on it or of a `+` it leads; a `+=`; a by-reference call that keeps them; or a rewrite of its
     * values in place. The one rule rewritesWhole() and a block's unread `=` decide by.
     */
    private function keepsEveryKey(Node $write, string $varName): bool
    {
        $value = $this->chainedValue($write instanceof Expr ? $write : null, $varName);
        $names = fn (?Expr $expr): bool => $expr instanceof Variable && $expr->name === $varName;

        return match (true) {
            $value instanceof BinaryOp\Plus => $names($value->left),
            $value instanceof FuncCall => $value->name instanceof Name
                && in_array($value->name->toLowerString(), self::KEEPS_FIRST_KEYS, true)
                && $this->takesFirst($value, $varName),
            $value instanceof StaticCall => $this->callsArrOn($value, 'add', $varName),
            $write instanceof Plus => $names($write->var),
            $write instanceof CallLike && ! $write->isFirstClassCallable() && array_any(
                $write->getArgs(),
                fn (Arg $arg): bool => $names($arg->value)
                    && resolve(ByReferenceArguments::class)->keepsStringKeys($write, $arg->value),
            ) => true,
            default => $this->rewritesValuesInPlace($write, $varName),
        };
    }

    /**
     * Whether a write rewrites the variable's values in place, keeping every key but not each value's type:
     * array_walk() or array_walk_recursive() on it, a by-reference `foreach` over it, or `Arr::set()` on it.
     */
    private function rewritesValuesInPlace(Node $write, string $varName): bool
    {
        $names = fn (Expr $expr): bool => $expr instanceof Variable && $expr->name === $varName;
        $inPlace = [ByReferenceEffect::WritesElements, ByReferenceEffect::WritesLeaves];

        return match (true) {
            $write instanceof Foreach_ => $write->byRef && $names($write->expr),
            $write instanceof CallLike => $this->callsArrOn($write, 'set', $varName)
                || (! $write->isFirstClassCallable() && array_any(
                    $write->getArgs(),
                    fn (Arg $arg): bool => $names($arg->value)
                        && in_array(resolve(ByReferenceArguments::class)->effect($write, $arg->value), $inPlace, true),
                )),
            default => false,
        };
    }

    /**
     * Whether a call passes the variable itself as its first argument.
     */
    private function takesFirst(CallLike $call, string $varName): bool
    {
        $first = $call->isFirstClassCallable() ? null : $call->getArgs()[0] ?? null;

        return $first !== null && $first->value instanceof Variable && $first->value->name === $varName;
    }

    /**
     * Whether a call is `Arr::{$method}()` on the variable itself.
     */
    private function callsArrOn(CallLike $call, string $method, string $varName): bool
    {
        return $call instanceof StaticCall && $call->class instanceof Name && $call->class->toString() === Arr::class
            && $call->name instanceof Identifier && $call->name->toLowerString() === $method
            && $this->takesFirst($call, $varName);
    }

    /**
     * The value a statement `$var = value;` assigns where it replaces every key the variable held, one that spreads
     * the variable into itself aside; null for any other statement.
     */
    private function replacingValue(Node\Stmt $stmt, string $varName): ?Expr
    {
        $value = $this->assignedValue($stmt, $varName);

        return $value !== null && ! ArrayWalk::spreadsVariable($value, $varName) ? $value : null;
    }

    /**
     * The value a statement assigns the whole variable, as `$var = value;` or a chain such as `$copy = $var = value;`
     * does; null for any other statement.
     */
    private function assignedValue(Node\Stmt $stmt, string $varName): ?Expr
    {
        return $this->chainedValue($stmt instanceof ExpressionStmt ? $stmt->expr : null, $varName);
    }

    /**
     * The value an expression assigns the whole variable, as `$var = value` or a chain such as `$copy = $var = value`
     * does; null for any other expression.
     */
    private function chainedValue(?Expr $expr, string $varName): ?Expr
    {
        $assigns = false;

        while ($expr instanceof Assign) {
            $assigns = $assigns || ($expr->var instanceof Variable && $expr->var->name === $varName);
            $expr = $expr->expr;
        }

        return $assigns ? $expr : null;
    }

    /**
     * Collect a variable's writes across an `if` chain, whose bodies exclude each other; with no `else`, none may run.
     *
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    private function collectIfChainAssignments(
        If_ $if,
        string $varName,
        bool $isConditional,
        ArrayWalk $walk,
        bool $topLevel,
    ): void {
        $bodies = [$if->stmts, ...array_column($if->elseifs, 'stmts')];

        if ($if->else !== null) {
            $bodies[] = $if->else->stmts;
        }

        $this->collectExclusiveAssignments($bodies, $if->else === null, $varName, $isConditional, $walk, $topLevel);
    }

    /**
     * Collect a variable's writes across bodies of which one runs, or none where $mayRunNone. Each body writes into its
     * own copy of the walk, which joins them (ArrayWalk::branches()); a body that exits elsewhere never reaches it.
     *
     * @param  non-empty-list<array<Node\Stmt>>  $bodies
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    private function collectExclusiveAssignments(
        array $bodies,
        bool $mayRunNone,
        string $varName,
        bool $isConditional,
        ArrayWalk $walk,
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

        $walks = [];

        foreach ($bodies as $body) {
            $branch = clone $walk;
            $this->collectVariableArrayAssignments($body, $varName, $isConditional, $branch, $topLevel, nested: true);
            $walks[] = $branch;
        }

        $walk->branches($walks, $mayRunNone, function (MethodAnalysis $joined, array $branches, array $notes): void {
            $this->recordJoinDrops($joined, $branches, $notes, fn (Expr $write): array => [$write, 'walk-branch-join']);
        });
    }

    /**
     * Record each branch value a join left out beside a typed one, naming the last untyped write of its key the branch
     * saw, and warn of each: it still reaches the read.
     *
     * @param  list<TAnalysis>  $branches
     * @param  list<list<UntypedKeyWrite>>  $notes  the untyped writes each branch saw
     * @param  Closure(Expr): LeftOutArm  $leftOut  one value left out, beside its join's site
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     */
    private function recordJoinDrops(MethodAnalysis $joined, array $branches, array $notes, Closure $leftOut, array $returns = []): void
    {
        if (array_merge(...$notes) === []) {
            $this->warnOfDroppedReturns($joined, $branches, $returns);

            return;
        }

        $types = array_map(
            fn (MethodAnalysis $branch): array => array_column($branch->properties, 'type', 'name'),
            $branches,
        );

        foreach ($joined->properties as $at => $property) {
            if ($property['type'] === 'unknown') {
                continue;
            }

            $left = [];

            foreach ($types as $index => $branchTypes) {
                $write = ($branchTypes[$property['name']] ?? null) === 'unknown'
                    ? $this->lastUntypedWrite($property['name'], $notes[$index])
                    : null;

                if ($write !== null) {
                    $left[] = $leftOut($write);
                }
            }

            if ($left !== []) {
                $union = [...ValueResult::unknown(), 'type' => $property['type']];
                $union = DroppedUnionArms::leftOutOfJoin($union, $left, $this->analysisScope());
                $joined->properties[$at]['type'] = $union['type'];
            }
        }
    }

    /**
     * Warn of each method return whose branch left a key untyped beside a typed one, a drop no untyped write names,
     * through DroppedUnionArms::leftBeside(); in the gap mode the key takes the gap arm it leaves.
     *
     * @param  list<TAnalysis>  $branches
     * @param  list<Node>  $returns  each branch's returned value
     */
    private function warnOfDroppedReturns(MethodAnalysis $joined, array $branches, array $returns): void
    {
        if ($returns === []) {
            return;
        }

        $types = array_map(
            fn (MethodAnalysis $branch): array => array_column($branch->properties, 'type', 'name'),
            $branches,
        );

        // An untyped branch joins gap arms alone to `unknown`, so a key that is not `unknown` holds a typed arm.
        foreach ($joined->properties as $at => $property) {
            if ($property['type'] === 'unknown') {
                continue;
            }

            foreach ($types as $index => $branchTypes) {
                if (! isset($returns[$index]) || ($branchTypes[$property['name']] ?? null) !== 'unknown') {
                    continue;
                }

                $id = DroppedUnionArms::leftBeside(
                    $this->analysisScope(), 'merge-branch-join', $returns[$index], $property['type'], $property['name'],
                );

                if ($id !== null) {
                    $joined->properties[$at]['type'] = VisibleGaps::withArm($joined->properties[$at]['type'], $id);
                }
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
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    private function collectNestedArrayAssignments(
        TryCatch|Switch_|Block|Do_ $stmt,
        string $varName,
        bool $isConditional,
        ArrayWalk $walk,
        bool $topLevel,
    ): void {
        if ($stmt instanceof Block) {
            $block = $stmt->stmts;
            $this->collectVariableArrayAssignments($block, $varName, $isConditional, $walk, $topLevel, nested: true);

            return;
        }

        if ($stmt instanceof Do_) {
            $body = array_values($stmt->stmts);
            $jump = array_find_key($body, $this->holdsBreakOrContinue(...)) ?? count($body);
            $head = array_slice($body, 0, $jump);
            $tail = array_slice($body, $jump);

            $this->collectVariableArrayAssignments($head, $varName, $isConditional, $walk, $topLevel, nested: true);
            $this->collectVariableArrayAssignments($tail, $varName, true, $walk, $topLevel, nested: true);

            return;
        }

        if ($stmt instanceof Switch_) {
            foreach ($stmt->cases as $case) {
                $this->collectVariableArrayAssignments($case->stmts, $varName, true, $walk, $topLevel, nested: true);
            }

            return;
        }

        $this->collectVariableArrayAssignments($stmt->stmts, $varName, true, $walk, $topLevel, nested: true);

        foreach ($stmt->catches as $catch) {
            $this->collectVariableArrayAssignments($catch->stmts, $varName, true, $walk, $topLevel, nested: true);
        }

        if ($stmt->finally !== null) {
            $finally = $stmt->finally->stmts;
            $this->collectVariableArrayAssignments($finally, $varName, $isConditional, $walk, $topLevel, nested: true);
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
     * Read a value a write of the variable assigns, each read of the variable in it standing for the keys $state holds.
     *
     * PHP evaluates the value before the write lands. Where $state cannot spell those keys, the value is read as the
     * body's other reads read it, and the miss is counted for walkedAnalysis().
     *
     * @template TRead
     *
     * @param  Closure(): MethodAnalysis  $state  the keys the walk holds, asked for only where the value reads them
     * @param  Closure(Expr): TRead  $read
     * @return TRead
     */
    private function readAgainstWalk(Expr $value, string $varName, Closure $state, Closure $read): mixed
    {
        if (! $this->readsVariableIn($value, $varName)) {
            return $read($value);
        }

        $state = $state();

        // A walk holds no key before its first whole write, while a parameter may hold its caller's, read as before.
        if ($state->properties === []) {
            return $read($value);
        }

        // A partly read state may hold the key read unread.
        if (! $state->readsCompletely) {
            $this->selfReadsMissed++;

            return $read($value);
        }

        $held = clone $state;
        resolve(IndexSignatureReconciler::class)->reconcile($held);
        $bound = new InlineObjectResult()->of($held);
        $optional = array_filter($held->properties, static fn (array $property): bool => $property['optional']);

        // A key a path left unset holds no MissingValue, so its bare read throws, and `??` reads past it.
        if ($optional !== [] && array_all($optional, static fn (array $property): bool => KeyPresence::ofRow($property)->isAbsentOnly())) {
            $bound['absentKeys'] = true;
        }

        $alias = AnalysisScope::walkAlias($varName);
        [$copy] = new NodeTraverser(new CloningVisitor, new class($varName, $alias) extends NodeVisitorAbstract
        {
            /**
             * Hold the variable's name and the name its reads take.
             */
            public function __construct(private readonly string $from, private readonly string $to) {}

            /**
             * Rename a variable node the copy holds, when it names the variable.
             */
            public function enterNode(Node $node): null
            {
                if ($node instanceof Variable && $node->name === $this->from) {
                    $node->name = $this->to;
                }

                return null;
            }
        })->traverse([$value]);

        if (! $copy instanceof Expr) {
            return $read($value); // @codeCoverageIgnore
        }

        $dropped = DroppedUnionArms::dropped();
        $result = $this->analysisScope()->within(
            function (AnalysisScope $scope) use ($alias, $bound): void {
                $scope->varValueBindings[$alias] = $bound;
            },
            fn (): mixed => $read($copy),
        );

        if (DroppedUnionArms::dropped() === $dropped) {
            return $result;
        }

        // A member the read cannot type, such as one naming a class, was left out, though it may be what is sent.
        $this->selfReadsMissed++;

        return $read($value);
    }

    /**
     * Whether an expression reads the variable anywhere within it.
     */
    private function readsVariableIn(Expr $expr, string $varName): bool
    {
        $reads = fn (Node $node): bool => $node instanceof Variable && $node->name === $varName;

        return new NodeFinder()->findFirst($expr, $reads) !== null;
    }

    /**
     * Whether a write that may set a key the frame's `@return` types reads the variable it writes: a whole write or
     * `+=` of the variable, which may set any key, or a write of one key a literal names typed, or none names.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function typedWriteReadsVariable(array $stmts, string $varName): bool
    {
        $scope = $this->analysisScope();

        if ($scope->typedKeys === []) {
            return false;
        }

        return new NodeFinder()->findFirst($stmts, function (Node $node) use ($scope, $varName): bool {
            if (! ($node instanceof Assign || $node instanceof CoalesceAssign || $node instanceof Plus)
                || ! $this->readsVariableIn($node->expr, $varName)
            ) {
                return false;
            }

            if ($node->var instanceof Variable) {
                return $node->var->name === $varName;
            }

            if (! $node->var instanceof ArrayDimFetch || ! $node->var->var instanceof Variable
                || $node->var->var->name !== $varName || $node->var->dim === null
            ) {
                return false;
            }

            $dim = $node->var->dim;

            // A key built around a variable is an index signature, which no `@return` member types.
            return match (true) {
                $dim instanceof String_, $dim instanceof Int_ => $scope->typesKey((string) $dim->value),
                $dim instanceof InterpolatedString, $dim instanceof Concat => false,
                default => true,
            };
        }) !== null;
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
     *
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    private function writesUnreadKey(Node\Stmt $stmt, string $varName, ArrayWalk $walk): bool
    {
        $write = $stmt instanceof ExpressionStmt ? $stmt->expr : null;
        $target = $write === null ? null : $this->writeTarget($write);

        if (! $target instanceof ArrayDimFetch || $this->keyRoot($target) !== $varName) {
            return false;
        }

        $key = $target->var instanceof Variable && $target->dim !== null ? $this->namedKey($target->dim) : null;

        // The key-write arm reads an assignment to a named key; anything else keeps only a key the walk already holds.
        return $key === null
            || (! $write instanceof Assign && ! $walk->holds($key));
    }

    /**
     * Take each named key an unset() takes from the variable, or make it optional where the unset() may not run. An
     * unset() of a key namedUnsetKey() cannot name may take any key, and one of the variable itself takes them all, so
     * either leaves the variable not read completely.
     *
     * @param  ArrayWalk<TAnalysis>  $walk
     */
    private function removeUnsetKeys(Unset_ $stmt, string $varName, bool $isConditional, ArrayWalk $walk): void
    {
        foreach ($stmt->vars as $unset) {
            $key = $this->namedUnsetKey($unset, $varName);

            if ($key !== null || $this->keyRoot($unset) === $varName) {
                $walk->unset($key, $isConditional);
            }
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

        return $key === null ? null : $this->resolveKeyName($key, $this->analysisScope());
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
