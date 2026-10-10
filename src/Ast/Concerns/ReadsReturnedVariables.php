<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
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

    /** Reads that publish only part of what they read; variableBranch() skips a variable whose walk raises it. */
    private int $lenientReads = 0;

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
     * A new, empty analysis of the host's own type, for a variable's walk to fill.
     *
     * @return TAnalysis
     */
    abstract protected function newVariableAnalysis(): MethodAnalysis;

    /**
     * Resolve properties from a method that builds an array variable and returns it.
     *
     * Handles: $data = [...]; $data['key'] = expr; if (...) { $data['key'] = expr; } return $data;
     * It reads a variable the gate rejects too, publishing what the walk sees, and counts that as a lenient read.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis
     */
    protected function resolveVariableReturnAnalysis(array $stmts, string $varName, bool $topLevel = true): MethodAnalysis
    {
        if (! $this->readsVariableArray($stmts, $varName)) {
            $this->lenientReads++;
        }

        return $this->walkVariable($stmts, $varName, $topLevel);
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

        foreach ($this->collectVariableWrites($stmts) as [$written, $node]) {
            if ($written !== $varName) {
                continue;
            }

            $target = $this->writeTarget($node);

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
     * Assignments inside a branch, a loop, a `try` body, a `catch` or a `case` are marked as optional, except those a
     * `do` body makes before its first `break` or `continue`, which always run. A whole-array write that analyzes as
     * nothing counts as a lenient read, since its keys are then unknown.
     *
     * @param  array<Node\Stmt>  $stmts
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

            if ($this->writesUnreadKey($stmt, $varName, $into)) {
                $this->lenientReads++;

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
                    $this->lenientReads++;

                    continue;
                }

                $this->mergeWholeArrayWrite($into, $baseAnalysis, $isConditional);

                continue;
            }

            // $var += [...] — PHP's union keeps every key already set, so only the new keys join, and a key the left
            // side keeps drops the right side's channels along with its value.
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Plus
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName) {
                $addedAnalysis = $this->analyzeArrayExpression($stmt->expr->expr, $topLevel);

                if ($addedAnalysis === null) {
                    $this->lenientReads++;

                    continue;
                }

                $present = array_column($into->properties, 'name');
                $added = [];

                foreach ($addedAnalysis->properties as $prop) {
                    if (in_array($prop['name'], $present, true)) {
                        $addedAnalysis->forgetChannels($prop['name']);
                    } else {
                        $added[] = $prop;
                    }
                }

                $addedAnalysis->properties = $added;
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
                $isIndexSignature = JsEmitter::isIndexSignatureKey($keyName);
                $optional = ! $isIndexSignature && ($isConditional || $result['optional']);

                if ($isIndexSignature) {
                    $result = ValueResult::asIndexSignatureValue($result);
                }

                $existingIndex = null;

                foreach ($into->properties as $index => $existing) {
                    if ($existing['name'] === $keyName) {
                        $existingIndex = $index;

                        break;
                    }
                }

                // A re-assigned key is the last write winning, in the first write's position: every stale channel
                // goes before addProperty() routes this result's own, and the entry it appends is folded back over
                // the earlier one.
                $into->forgetChannels($keyName);

                $appendedIndex = count($into->properties);

                $into->addProperty($keyName, $result, $optional);

                // A re-set key is absent where its new value vanishes, or where this write is skipped and the old one
                // was absent; an index signature is never optional.
                if ($existingIndex !== null && isset($into->properties[$appendedIndex])) {
                    $appended = $into->properties[$appendedIndex];
                    $appended['optional'] = ! $isIndexSignature && ($result['optional']
                        || ($isConditional && $into->properties[$existingIndex]['optional']));

                    $into->properties[$existingIndex] = $appended;
                    array_splice($into->properties, $appendedIndex, 1);
                }

                continue;
            }

            if ($stmt instanceof If_) {
                $this->collectVariableArrayAssignments($stmt->stmts, $varName, true, $into, $topLevel);

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectVariableArrayAssignments($elseif->stmts, $varName, true, $into, $topLevel);
                }

                if ($stmt->else !== null) {
                    $this->collectVariableArrayAssignments($stmt->else->stmts, $varName, true, $into, $topLevel);
                }
            }

            // Loop bodies are conditional: a loop may execute zero times.
            if ($stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof While_) {
                $this->collectVariableArrayAssignments($stmt->stmts, $varName, true, $into, $topLevel);
            }
        }
    }

    /**
     * The branches a closure's returns can merge, none when no branch sets a key.
     *
     * One per returned array, `[]` included, one per returned variable the walk reads completely, however often it is
     * returned, and one per other value mergedValueBranch() reads. A return none of them reads is a lenient read.
     *
     * @return list<TAnalysis>
     */
    protected function closureReturnBranches(Expr $closure): array
    {
        $stmts = $closure instanceof ClosureExpr ? $closure->stmts : [];
        $branches = [];
        $read = [];

        foreach ($this->resolveClosureReturnExpressions($closure) as $returned) {
            if ($returned instanceof Array_) {
                $branches[] = $this->mergedArrayAnalysis($returned);

                continue;
            }

            if ($returned instanceof Variable && is_string($returned->name)) {
                if (isset($read[$returned->name])) {
                    continue;
                }

                $read[$returned->name] = true;
                $branch = $this->variableBranch($stmts, $returned->name, topLevel: false);
            } else {
                $branch = $this->mergedValueBranch($returned);
            }

            // Its keys are unknown, so a variable holding this merge is not read completely either.
            if ($branch === null) {
                $this->lenientReads++;

                continue;
            }

            $branches[] = $branch;
        }

        // A guard's `return []` merges nothing, so beside a branch that sets a key it is a branch like any other.
        return array_any($branches, fn (MethodAnalysis $branch): bool => $branch->properties !== []) ? $branches : [];
    }

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
     * A merged value's branch, or null when the host does not read it completely, as variableBranch() decides.
     *
     * @return TAnalysis|null
     */
    private function mergedValueBranch(Expr $expr): ?MethodAnalysis
    {
        $lenientReads = $this->lenientReads;
        $analysis = $this->mergedValueAnalysis($expr);

        return $this->lenientReads === $lenientReads ? $analysis : null;
    }

    /**
     * A returned variable's branch, or null when the walk does not read it completely: the gate rejects a whole write,
     * or reading it counts a lenient read, as a model-less `parent::toArray()` or an unreadable helper does.
     *
     * @param  array<Node\Stmt>  $stmts
     * @return TAnalysis|null
     */
    private function variableBranch(array $stmts, string $varName, bool $topLevel = true): ?MethodAnalysis
    {
        if (! $this->readsVariableArray($stmts, $varName)) {
            return null;
        }

        $lenientReads = $this->lenientReads;
        $analysis = $this->walkVariable($stmts, $varName, $topLevel);

        return $this->lenientReads === $lenientReads ? $analysis : null;
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
     * Merge a whole-array write into the keys a variable already holds. A new key is optional when the write is
     * conditional or its value can vanish; a key set again keeps its first position and takes the last value, optional
     * when that value can vanish or a conditional write leaves an optional old one.
     */
    private function mergeWholeArrayWrite(MethodAnalysis $into, MethodAnalysis $write, bool $isConditional): void
    {
        $properties = $into->properties;
        $names = array_column($properties, 'name');
        $written = [];

        // A key the write itself repeats is one key with its last value, as the same literal returned would publish.
        foreach ($write->properties as $prop) {
            $written[$prop['name']] = $prop;
        }

        foreach ($written as $prop) {
            $index = array_search($prop['name'], $names, true);

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
     * Collect a variable's writes inside a `try`, `switch`, `do` or bare block: a catch or a case may not run, nor a
     * `try` body finish, so their writes are conditional, while a bare block and a `finally` keep the caller's
     * condition. A `do` body, which runs once, keeps it too, until a statement that can `break` or `continue`.
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
     * Whether a statement writes the variable through a key the walk does not read: a dynamic, appended or nested key,
     * an unset() of the variable or a key of it, or a compound write or an increment of a key the walk does not hold.
     */
    private function writesUnreadKey(Node\Stmt $stmt, string $varName, MethodAnalysis $into): bool
    {
        if ($stmt instanceof Unset_) {
            return array_any($stmt->vars, fn (Expr $unset): bool => $this->keyRoot($unset) === $varName);
        }

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
     * The key a write's dim names, a literal string or a pattern interpolatedKeyName() accepts; null for any other.
     */
    private function namedKey(Expr $dim): ?string
    {
        return $dim instanceof String_
            ? $this->literalKeyName($dim->value, $this->subjectReflection())
            : $this->interpolatedKeyName($dim);
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
