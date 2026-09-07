<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;

/**
 * Arithmetic, comparison, logical, and unary +/- operators.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class BinaryOpHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [
            BinaryOp::class,
            BooleanNot::class,
            Instanceof_::class,
            Isset_::class,
            Empty_::class,
            UnaryMinus::class,
            UnaryPlus::class,
        ];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // Unary +/- always yield a number; non-literal operands are assumed numeric (variable types aren't tracked).
        if ($expr instanceof UnaryMinus || $expr instanceof UnaryPlus) {
            return ['type' => 'number', 'optional' => false];
        }

        // Arithmetic always yields a number. Also catches `(int) round(...) / 2`: PHP precedence binds the
        // cast tighter than the division, so the outer node is a BinaryOp\Div rather than a Cast.
        if ($expr instanceof BinaryOp\Plus
            || $expr instanceof BinaryOp\Minus
            || $expr instanceof BinaryOp\Mul
            || $expr instanceof BinaryOp\Div
            || $expr instanceof BinaryOp\Mod
            || $expr instanceof BinaryOp\Pow
        ) {
            return ['type' => 'number', 'optional' => false];
        }

        if ($expr instanceof BinaryOp\Concat) {
            return ['type' => 'string', 'optional' => false];
        }

        // Comparison, logical, and type-test operators always produce a boolean. PHP's &&/|| return bool,
        // unlike JS — even as a null-guard (`$this->x && $this->x->y`), no false|T union is needed.
        if ($expr instanceof BinaryOp\Identical
            || $expr instanceof BinaryOp\NotIdentical
            || $expr instanceof BinaryOp\Equal
            || $expr instanceof BinaryOp\NotEqual
            || $expr instanceof BinaryOp\Greater
            || $expr instanceof BinaryOp\GreaterOrEqual
            || $expr instanceof BinaryOp\Smaller
            || $expr instanceof BinaryOp\SmallerOrEqual
            || $expr instanceof BinaryOp\BooleanAnd
            || $expr instanceof BinaryOp\BooleanOr
            || $expr instanceof BinaryOp\LogicalAnd
            || $expr instanceof BinaryOp\LogicalOr
            || $expr instanceof BinaryOp\LogicalXor
            || $expr instanceof BooleanNot
            || $expr instanceof Instanceof_
            || $expr instanceof Isset_
            || $expr instanceof Empty_
        ) {
            return ['type' => 'boolean', 'optional' => false];
        }

        // Spaceship comparison produces -1|0|1.
        if ($expr instanceof BinaryOp\Spaceship) {
            return ['type' => 'number', 'optional' => false];
        }

        // Coalesce belongs to CoalesceHandler; other BinaryOp kinds (bitwise, shift) are simply not typed.
        return null;
    }
}
