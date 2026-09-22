<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Name;

/**
 * The `instanceof` grammar narrowing reads: an `||` chain split into operands, and one operand read as a test.
 *
 * Shared by the early-exit guard pass and ternary receiver narrowing, which differ only in what an operand must be.
 *
 * @phpstan-type InstanceofTest = array{Expr, class-string}
 *
 * @internal
 */
trait ReadsInstanceofChains
{
    /**
     * The operands of an `||` chain, left to right; any other condition is its own single operand.
     *
     * @return non-empty-list<Expr>
     */
    private function orOperands(Expr $cond): array
    {
        if ($cond instanceof BooleanOr) {
            return [...$this->orOperands($cond->left), ...$this->orOperands($cond->right)];
        }

        return [$cond];
    }

    /**
     * The subject and class of `<subject> instanceof <Class>` when it names a loadable class or interface, else null.
     *
     * @return InstanceofTest|null
     */
    private function instanceofTest(Expr $expr): ?array
    {
        if (! $expr instanceof Instanceof_ || ! $expr->class instanceof Name) {
            return null;
        }

        $class = $expr->class->toString();

        return class_exists($class) || interface_exists($class) ? [$expr->expr, $class] : null;
    }
}
