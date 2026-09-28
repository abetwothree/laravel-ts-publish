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

/**
 * The early-exit `instanceof` guard pass. Hosts must also use CollectsLocalVarBindings: its write
 * collection is what decides a binding is safe, and both passes read the same statement list.
 *
 * @phpstan-import-type VariableWrites from CollectsLocalVarBindings
 *
 * @internal
 */
trait CollectsInstanceofGuards
{
    use ReadsInstanceofChains;

    /**
     * Bind variables an early-exit `if (! $x instanceof C)` guard proves to be a C, for the statements after it.
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
                if (! $this->writtenAfterTest($name, $test, $stmt, $writes)) {
                    $scope->varGuardBindings[$name] = ['classes' => [$class], 'after' => $stmt->getEndFilePos()];
                }
            }
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
     * Whether a write to the variable can change it once the guard has tested it: one that does not end before the
     * test, and does not sit in the guard's own body, which always exits.
     *
     * @param  VariableWrites  $writes
     */
    private function writtenAfterTest(string $name, Expr $test, If_ $guard, array $writes): bool
    {
        foreach ($this->writesFrom($name, $test->getStartFilePos(), $writes) as $node) {
            if ($node->getStartFilePos() <= $guard->cond->getEndFilePos() || $node->getEndFilePos() > $guard->getEndFilePos()) {
                return true;
            }
        }

        return false;
    }
}
