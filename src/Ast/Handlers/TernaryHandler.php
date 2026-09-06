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

        $wrapResult = $this->unambiguousArm($ifResult, $elseResult, 'enumFqcn');
        $directResult = $this->unambiguousArm($ifResult, $elseResult, 'directEnumFqcn');

        // Either arm being itself mixed (e.g. a nested ternary) makes wrap/direct unattributable —
        // decline rather than let one arm masquerade as both, and let the caller's own fallback stand.
        if ($wrapResult === null || $directResult === null) {
            return $result;
        }

        $result['wrapIsCollection'] = str_ends_with(rtrim(str_replace('| null', '', $wrapResult['type'])), '[]');
        $result['directIsArray'] = str_ends_with(rtrim(str_replace('| null', '', $directResult['type'])), '[]');

        return $result;
    }

    /**
     * The arm that carries only `$key` and not the other FQCN channel — null when neither arm
     * qualifies (both/neither carry it alone), which is the ambiguous case the caller declines.
     *
     * @param  ValueExpressionResult  $ifResult
     * @param  ValueExpressionResult  $elseResult
     * @param  'enumFqcn'|'directEnumFqcn'  $key
     * @return ValueExpressionResult|null
     */
    private function unambiguousArm(array $ifResult, array $elseResult, string $key): ?array
    {
        $other = $key === 'enumFqcn' ? 'directEnumFqcn' : 'enumFqcn';

        if (isset($ifResult[$key]) && ! isset($ifResult[$other])) {
            return $ifResult;
        }

        if (isset($elseResult[$key]) && ! isset($elseResult[$other])) {
            return $elseResult;
        }

        return null;
    }
}
