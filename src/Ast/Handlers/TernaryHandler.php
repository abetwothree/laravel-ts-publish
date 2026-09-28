<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Ternary;

/**
 * Ternary and Elvis expressions — both arms resolved through the engine and unioned.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type TernaryArms = array{Expr, Expr}
 * @phpstan-type ArmResults = array{ValueExpressionResult, ValueExpressionResult}
 *
 * @internal
 */
final class TernaryHandler implements ExpressionHandler
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;
    use NarrowsInstanceofSubjects;
    use ReadsInstanceofChains;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Ternary::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof Ternary) {
            return $this->analyzeTernary($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Analyze a ternary or Elvis expression, unioning both branches.
     *
     * In Elvis (`$cond ?: $else`) the parser leaves `if` null, so the truthy value is `$cond` itself.
     * An `instanceof` condition narrows its subject for the arm it proves only.
     *
     * @return ValueExpressionResult
     */
    private function analyzeTernary(Ternary $expr, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $arms = [$expr->if ?? $expr->cond, $expr->else];
        $proof = $expr->if === null ? null : $this->instanceofProof($expr->cond);
        $narrowed = null;

        if ($proof !== null) {
            $proven = $arms[$proof[2]];
            $narrowed = $this->resolveNarrowed($proof[0], $proof[1], $proven, $scope, fn (): array => $engine->resolve($proven));
        }

        if ($proof === null || $narrowed === null) {
            return $this->recordMixedArmShapes(ValueResult::analyzeClosureUnion($arms, $engine, $scope), $arms, $engine);
        }

        $other = $engine->resolve($arms[1 - $proof[2]]);
        $results = $proof[2] === 0 ? [$narrowed, $other] : [$other, $narrowed];

        // This path resolves its arms itself, so it records its own drops: analyzeClosureUnion() never sees them.
        foreach ($results as $index => $armResult) {
            if ($armResult['type'] === 'unknown') {
                DroppedUnionArms::record($arms[$index], $scope, 'ternary-narrowed');
            }
        }

        return $this->recordMixedArmShapes(ValueResult::unionResults($results), $arms, $engine, $results);
    }

    /**
     * A mixed union (one arm wraps via EnumResource, the other reads directly) collapses to one
     * deduped bare type name, so the merged result alone can't tell an array-shaped arm from a
     * scalar one — re-resolving each arm here, while still distinct, is the only place that survives.
     *
     * @param  ValueExpressionResult  $result
     * @param  TernaryArms  $arms
     * @param  ArmResults|null  $armResults  both arms, under any narrowing
     * @return ValueExpressionResult
     */
    private function recordMixedArmShapes(array $result, array $arms, ExpressionEngine $engine, ?array $armResults = null): array
    {
        if (! isset($result['enumFqcn'], $result['directEnumFqcn']) || $result['enumFqcn'] !== $result['directEnumFqcn']) {
            return $result;
        }

        // Reuse the narrowed resolution when there was one: resolving again here would drop the narrowing
        // and let two resolutions of the same arm disagree by construction.
        [$ifResult, $elseResult] = $armResults ?? [$engine->resolve($arms[0]), $engine->resolve($arms[1])];

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
