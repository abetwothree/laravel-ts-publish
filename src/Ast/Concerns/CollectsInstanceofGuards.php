<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * The early-exit `instanceof` guard pass. Hosts must also use CollectsLocalVarBindings: its write
 * counting is what decides a binding is safe, and both passes read the same statement list.
 *
 * @internal
 */
trait CollectsInstanceofGuards
{
    use ReadsInstanceofChains;

    /**
     * Bind variables an early-exit `if (! $x instanceof C)` guard proves to be a C for the rest of the body.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function collectInstanceofGuards(array $stmts, AnalysisScope $scope): void
    {
        $writeCounts = array_count_values($this->collectWrittenVariableNames($stmts));

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof If_ || $stmt->elseifs !== [] || $stmt->else !== null || ! $this->alwaysExits($stmt->stmts)) {
                continue;
            }

            foreach ($this->negatedInstanceofs($stmt->cond) as [$name, $class]) {
                if (($writeCounts[$name] ?? 0) <= 1 && ! $this->readsVariable($stmt->stmts, $name)) {
                    $scope->varClassBindings[$name] = [$class];
                }
            }
        }
    }

    /**
     * Whether a guard body mentions the variable it guards — the one branch proving it is NOT that class.
     *
     * This binding is method-wide with no position tracking, so it would otherwise also be in force while
     * the guard's own body is analyzed, and that body is live: `analyzeThisMethodSpread()` reads the first
     * return, which for a guarded method is the guard's own.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function readsVariable(array $stmts, string $name): bool
    {
        return new NodeFinder()->findFirst(
            $stmts,
            fn (Node $node): bool => $node instanceof Variable && $node->name === $name,
        ) !== null;
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
     * Every `! $var instanceof Class` operand of a condition, through `||` chains.
     *
     * @return list<array{string, class-string}>
     */
    private function negatedInstanceofs(Expr $cond): array
    {
        $negated = [];

        foreach ($this->orOperands($cond) as $operand) {
            $test = $operand instanceof BooleanNot ? $this->instanceofTest($operand->expr) : null;

            if ($test !== null && $test[0] instanceof Variable && is_string($test[0]->name)) {
                $negated[] = [$test[0]->name, $test[1]];
            }
        }

        return $negated;
    }
}
