<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * The `instanceof` grammar narrowing reads: an `||` chain split into operands, one operand read as a test, and what a
 * ternary's condition proves about the arm that runs.
 *
 * Shared by the early-exit guard pass and ternary narrowing, which differ only in what an operand must be.
 *
 * @phpstan-type InstanceofTest = array{Expr, class-string}
 * @phpstan-type InstanceofProof = array{Expr, non-empty-list<class-string>, 0|1}
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

    /**
     * What a ternary's condition proves: the subject an `instanceof` test, or an `||` chain of them on one read, tests,
     * the classes it holds when the test passes, and the arm that runs then, 0 for the true arm, or 1 for the false arm
     * of a negated test. Null for any other condition.
     *
     * @return InstanceofProof|null
     */
    private function instanceofProof(Expr $cond): ?array
    {
        $arm = $cond instanceof BooleanNot ? 1 : 0;
        $subject = null;
        $classes = [];

        foreach ($this->orOperands($cond instanceof BooleanNot ? $cond->expr : $cond) as $operand) {
            $test = $this->instanceofTest($operand);

            if ($test === null || ($subject !== null && ! $this->isSameReadPath($subject, $test[0]))) {
                return null;
            }

            $subject = $test[0];
            $classes[] = $test[1];
        }

        return [$subject, array_values(array_unique($classes)), $arm];
    }

    /**
     * Whether two expressions spell the same variable or property read.
     *
     * A method call is never the same read: a second call may return another value than the one the test saw.
     */
    private function isSameReadPath(Expr $left, Expr $right): bool
    {
        if ($left instanceof Variable && $right instanceof Variable) {
            return is_string($left->name) && $left->name === $right->name;
        }

        if (! ($left instanceof PropertyFetch && $right instanceof PropertyFetch)
            && ! ($left instanceof NullsafePropertyFetch && $right instanceof NullsafePropertyFetch)
        ) {
            return false;
        }

        return $left->name instanceof Identifier
            && $right->name instanceof Identifier
            && $left->name->toString() === $right->name->toString()
            && $this->isSameReadPath($left->var, $right->var);
    }
}
