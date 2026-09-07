<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;

/**
 * Analyze a null-coalescing expression (`$left ?? $right`).
 *
 * Doesn't delegate to ValueResult::analyzeClosureUnion(): that would leave `null` in twice
 * (`Order | null | Order`). Only operands contributing a result member get their channels merged.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CoalesceHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [BinaryOp\Coalesce::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof BinaryOp\Coalesce) {
            $leftResult = $engine->resolve($expr->left);
            $rightResult = $engine->resolve($expr->right);

            $leftType = $leftResult['type'];
            $rightType = $rightResult['type'];

            // Strip `| null` from the left: with a non-null fallback, null is never the final result.
            $leftType = ValueResult::stripNullArm($leftType);

            if ($leftType === 'unknown' || $leftType === '') {
                return ValueResult::mergeUnion([$rightType], [$rightResult]);
            }

            if ($rightType === 'unknown') {
                return ValueResult::mergeUnion([$leftType], [$leftResult]);
            }

            if ($leftType === $rightType) {
                return ValueResult::mergeUnion([$leftType], [$leftResult, $rightResult]);
            }

            return ValueResult::mergeUnion([$leftType, $rightType], [$leftResult, $rightResult]);
        }

        return null;
    }
}
