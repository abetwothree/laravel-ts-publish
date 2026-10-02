<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;

/**
 * Analyze a null-coalescing expression (`$left ?? $right`).
 *
 * Doesn't delegate to ValueResult::analyzeClosureUnion(): the left operand's `null` never reaches the result, so
 * its arm is unioned with that `null` stripped. Only operands contributing a result member get their channels merged.
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

            // Strip `| null` from the left: with a non-null fallback, null is never the final result.
            $leftResult['type'] = ValueResult::stripNullArm($leftResult['type']);

            // unionResults() leaves out an operand the engine could not type, so only the audit is told which one:
            // the left one alone when neither has a type.
            if ($leftResult['type'] === 'unknown') {
                DroppedUnionArms::record($expr->left, $scope, 'coalesce-left');
            } elseif ($rightResult['type'] === 'unknown') {
                DroppedUnionArms::record($expr->right, $scope, 'coalesce-right');
            }

            // Two arms that render alike are one member, unless they spell one name for two classes, which
            // unionResults() keeps apart.
            return ValueResult::unionResults([$leftResult, $rightResult]);
        }

        return null;
    }
}
