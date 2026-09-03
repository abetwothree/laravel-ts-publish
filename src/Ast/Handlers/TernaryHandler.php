<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Ternary;

/**
 * Ternary and Elvis expressions — both arms resolved through the engine and unioned.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class TernaryHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Ternary::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof Ternary) {
            return $this->analyzeTernary($expr, $engine);
        }

        return null;
    }

    /**
     * Analyze a ternary or Elvis expression, unioning both branches.
     *
     * In Elvis (`$cond ?: $else`) the parser leaves `if` null, so the truthy value is `$cond` itself.
     *
     * @return ValueExpressionResult
     */
    private function analyzeTernary(Ternary $expr, ExpressionEngine $engine): array
    {
        $ifExpr = $expr->if ?? $expr->cond;

        $result = ValueResult::analyzeClosureUnion([$ifExpr, $expr->else], $engine);

        return $this->recordMixedArmShapes($result, $ifExpr, $expr->else, $engine);
    }

    /**
     * A mixed union (one arm wraps via EnumResource, the other reads directly) collapses to one
     * deduped bare type name, so the merged result alone can't tell an array-shaped arm from a
     * scalar one — re-resolving each arm here, while still distinct, is the only place that survives.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    private function recordMixedArmShapes(array $result, Expr $ifExpr, Expr $elseExpr, ExpressionEngine $engine): array
    {
        if (! isset($result['enumFqcn'], $result['directEnumFqcn']) || $result['enumFqcn'] !== $result['directEnumFqcn']) {
            return $result;
        }

        $ifResult = $engine->resolve($ifExpr);
        $elseResult = $engine->resolve($elseExpr);
        $wrapResult = isset($ifResult['enumFqcn']) ? $ifResult : $elseResult;
        $directResult = isset($ifResult['directEnumFqcn']) ? $ifResult : $elseResult;

        $result['wrapIsCollection'] = str_ends_with(rtrim(str_replace('| null', '', $wrapResult['type'])), '[]');
        $result['directIsArray'] = str_ends_with(rtrim(str_replace('| null', '', $directResult['type'])), '[]');

        return $result;
    }
}
