<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
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
 * @internal
 */
final class TernaryHandler implements ExpressionHandler
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;
    use NarrowsInstanceofSubjects;
    use ReadsInstanceofChains;
    use ReadsNonNullGuards;

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
     * Each arm resolves under the reads the condition proves non-null where that arm runs, and an `instanceof`
     * condition narrows its subject for the arm it proves.
     *
     * @return ValueExpressionResult
     */
    private function analyzeTernary(Ternary $expr, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $arms = [$expr->if ?? $expr->cond, $expr->else];
        $nonNull = [$this->nonNullReads($expr->cond, true), $this->nonNullReads($expr->cond, false)];
        $proof = $expr->if === null ? null : $this->instanceofProof($expr->cond);
        $resolveArm = fn (int $arm): array => $this->resolveProvenNonNull($nonNull[$arm], $scope, fn (): array => $engine->resolve($arms[$arm]));
        /** @var array<0|1, ValueExpressionResult> $narrowed */
        $narrowed = [];

        if ($proof !== null) {
            $result = $this->resolveNarrowed($proof[0], $proof[1], $arms[$proof[2]], $scope, fn (): array => $resolveArm($proof[2]));

            if ($result !== null) {
                $narrowed[$proof[2]] = $result;
            }
        }

        if ($narrowed === [] && $nonNull === [[], []]) {
            return ValueResult::withEnumArmShapes(
                ValueResult::analyzeClosureUnion($arms, $engine, $scope),
                static fn (): array => array_map($engine->resolve(...), $arms),
            );
        }

        $results = [$narrowed[0] ?? $resolveArm(0), $narrowed[1] ?? $resolveArm(1)];

        // This path resolves its arms itself, so it records its own drops: analyzeClosureUnion() never sees them.
        foreach ($results as $index => $armResult) {
            if ($armResult['type'] === 'unknown') {
                DroppedUnionArms::record($arms[$index], $scope, 'ternary-narrowed');
            }
        }

        // The narrowed results, not a second resolution of the arms: that would drop the narrowing and let two
        // resolutions of the same arm disagree by construction.
        return ValueResult::withEnumArmShapes(ValueResult::unionResults($results), $results);
    }
}
