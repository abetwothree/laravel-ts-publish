<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * The guard pass: an early-exit `instanceof` guard narrows a variable, and an `if` condition proves reads non-null in
 * the blocks it runs and past the branches that exit. Hosts must also use CollectsLocalVarBindings: its write
 * collection is what decides a binding or a proof is safe, and both passes read the same statement list.
 *
 * @phpstan-import-type VariableWrites from CollectsLocalVarBindings
 *
 * @internal
 */
trait CollectsInstanceofGuards
{
    use ReadsInstanceofChains;
    use ReadsNonNullGuards;

    /**
     * Bind variables an early-exit `if (! $x instanceof C)` guard proves to be a C, for the statements after it, and
     * prove the reads the body's guards show non-null.
     *
     * The binding carries the offset the guard ends at, so a return placed before the guard, or the guard's own body,
     * still reads the variable unnarrowed. A variable written after the guard tests it is not bound at all.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function collectInstanceofGuards(array $stmts, AnalysisScope $scope): void
    {
        $writes = $this->collectVariableWrites($stmts);

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof If_ || $stmt->elseifs !== [] || $stmt->else !== null || ! $this->alwaysExits($stmt->stmts)) {
                continue;
            }

            foreach ($this->negatedInstanceofs($stmt->cond) as [$name, $class, $test]) {
                if (! $this->writtenWithin($name, $test->getStartFilePos(), null, [$stmt->stmts], $writes)) {
                    $scope->varGuardBindings[$name] = ['classes' => [$class], 'after' => $stmt->getEndFilePos()];
                }
            }
        }

        $this->proveGuardedReads($stmts, $scope, $writes);
    }

    /**
     * Prove the reads a closure's own guards show non-null while its body runs; an arrow function holds no statement.
     */
    protected function proveClosureGuards(Expr $closure, AnalysisScope $scope): void
    {
        if ($closure instanceof ClosureExpr) {
            $this->proveGuardedReads($closure->stmts, $scope, $this->collectVariableWrites($closure->stmts));
        }
    }

    /**
     * Whether a block's last statement leaves the function.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function alwaysExits(array $stmts): bool
    {
        $last = $stmts === [] ? null : $stmts[array_key_last($stmts)];

        return $last instanceof Return_ || ($last instanceof ExpressionStmt && $last->expr instanceof Throw_);
    }

    /**
     * Every `! $var instanceof Class` operand of a condition, through `||` chains, with the operand itself.
     *
     * @return list<array{string, class-string, Expr}>
     */
    private function negatedInstanceofs(Expr $cond): array
    {
        $negated = [];

        foreach ($this->orOperands($cond) as $operand) {
            $test = $operand instanceof BooleanNot ? $this->instanceofTest($operand->expr) : null;

            if ($test !== null && $test[0] instanceof Variable && is_string($test[0]->name)) {
                $negated[] = [$test[0]->name, $test[1], $operand];
            }
        }

        return $negated;
    }

    /**
     * Prove what a body's guards show, once every name it writes has lost the proofs it came in with: a closure's or a
     * called method's body may rewrite a read its caller's guard tested.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function proveGuardedReads(array $stmts, AnalysisScope $scope, array $writes): void
    {
        foreach ($writes as [$name]) {
            unset($scope->nonNullReads[$name]);
        }

        $this->proveBlockGuards($stmts, null, $scope, $writes);
    }

    /**
     * Prove what each `if` of one block shows, then read every block its statements hold, never a closure's body. $end
     * is the offset the block ends at, or null for a whole body.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  VariableWrites  $writes
     */
    private function proveBlockGuards(array $stmts, ?int $end, AnalysisScope $scope, array $writes): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof If_) {
                $this->proveIfGuards($stmt, $end, $scope, $writes);
            }

            foreach ($this->innerBlocks($stmt) as $block) {
                if ($block !== []) {
                    $this->proveBlockGuards($block, $block[array_key_last($block)]->getEndFilePos(), $scope, $writes);
                }
            }
        }
    }

    /**
     * Prove what one `if` chain shows: each branch's block runs where its condition holds and every earlier one failed,
     * the `else` where all failed, and the statements after the chain, up to $end, where the conditions of the leading
     * branches that always exit failed.
     *
     * @param  VariableWrites  $writes
     */
    private function proveIfGuards(If_ $if, ?int $end, AnalysisScope $scope, array $writes): void
    {
        $failed = [];
        $exiting = true;
        $exits = [];
        $pastChain = [];

        foreach ([$if, ...$if->elseifs] as $branch) {
            $this->proveBlock([...$failed, ...$this->nonNullReads($branch->cond, true)], $branch->stmts, $if, $scope, $writes);
            $failed = [...$failed, ...$this->nonNullReads($branch->cond, false)];
            $exiting = $exiting && $this->alwaysExits($branch->stmts);

            if ($exiting) {
                $exits[] = $branch->stmts;
                $pastChain = $failed;
            }
        }

        if ($if->else !== null) {
            $this->proveBlock($failed, $if->else->stmts, $if, $scope, $writes);
        }

        foreach ($pastChain as $read) {
            $root = $this->readRoot($read);

            // A body that always exits never reaches the statements after the chain, so neither does a write inside it.
            if ($root !== null && ! $this->writtenWithin($root, $if->cond->getStartFilePos(), $end, $exits, $writes)) {
                $this->proveNonNull([$read], $scope, $if->getEndFilePos(), $end);
            }
        }
    }

    /**
     * Prove reads for the statements of one block an `if` runs, unless a write inside the `if` can change the read.
     *
     * @param  list<Expr>  $reads
     * @param  array<Node\Stmt>  $block
     * @param  VariableWrites  $writes
     */
    private function proveBlock(array $reads, array $block, If_ $if, AnalysisScope $scope, array $writes): void
    {
        if ($block === []) {
            return;
        }

        $after = $block[0]->getStartFilePos() - 1;
        $before = $block[array_key_last($block)]->getEndFilePos() + 1;

        foreach ($reads as $read) {
            $root = $this->readRoot($read);

            if ($root !== null && ! $this->writtenWithin($root, $if->getStartFilePos(), $if->getEndFilePos(), [], $writes)) {
                $this->proveNonNull([$read], $scope, $after, $before);
            }
        }
    }

    /**
     * The statement blocks a statement holds: its branches, a loop's or a bare block's body, a `try`'s blocks and a
     * `switch`'s cases.
     *
     * @return array<array<Node\Stmt>>
     */
    private function innerBlocks(Node\Stmt $stmt): array
    {
        return match (true) {
            $stmt instanceof If_ => [
                $stmt->stmts,
                ...array_map(fn (ElseIf_ $elseif): array => $elseif->stmts, $stmt->elseifs),
                ...($stmt->else === null ? [] : [$stmt->else->stmts]),
            ],
            $stmt instanceof Foreach_, $stmt instanceof For_, $stmt instanceof While_, $stmt instanceof Do_,
            $stmt instanceof Block => [$stmt->stmts],
            $stmt instanceof TryCatch => [
                $stmt->stmts,
                ...array_map(fn (Catch_ $catch): array => $catch->stmts, $stmt->catches),
                ...($stmt->finally === null ? [] : [$stmt->finally->stmts]),
            ],
            $stmt instanceof Switch_ => array_map(fn (Case_ $case): array => $case->stmts, $stmt->cases),
            default => [],
        };
    }

    /**
     * Whether a write to the variable can land between two offsets: one that does not end before $from, nor start past
     * $to when it is set, and that sits in none of $exits, bodies that always exit before the reads it guards.
     *
     * @param  list<array<Node\Stmt>>  $exits
     * @param  VariableWrites  $writes
     */
    private function writtenWithin(string $name, int $from, ?int $to, array $exits, array $writes): bool
    {
        foreach ($this->writesFrom($name, $from, $writes) as $node) {
            $inExit = array_any($exits, fn (array $body): bool => $body !== []
                && $node->getStartFilePos() >= $body[0]->getStartFilePos()
                && $node->getEndFilePos() <= $body[array_key_last($body)]->getEndFilePos());

            if (($to === null || $node->getStartFilePos() <= $to) && ! $inExit) {
                return true;
            }
        }

        return false;
    }
}
