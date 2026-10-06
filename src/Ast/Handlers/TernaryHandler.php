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
            return ValueResult::withEnumArmShapes(
                ValueResult::analyzeClosureUnion($arms, $engine, $scope),
                static fn (): array => array_map($engine->resolve(...), $arms),
            );
        }

        $other = $engine->resolve($arms[1 - $proof[2]]);
        $results = $proof[2] === 0 ? [$narrowed, $other] : [$other, $narrowed];

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
