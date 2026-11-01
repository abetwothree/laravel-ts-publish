<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Ternary and Elvis expressions — both arms resolved through the engine and unioned.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class TernaryHandler implements ExpressionHandler, KeepsHeldObjects
{
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
        $operand = $this->resourceOperand($expr, $scope, $engine);

        if ($operand !== null) {
            return ValueResult::checkedOperand($operand);
        }

        $arms = [$expr->if ?? $expr->cond, $expr->else];
        $nonNull = [$this->nonNullReads($expr->cond, true), $this->nonNullReads($expr->cond, false)];
        $proof = $expr->if === null ? null : $this->instanceofProof($expr->cond);
        $resolveArm = fn (int $arm): array => $this->resolveProvenNonNull($nonNull[$arm], $scope, fn (): array => $engine->resolve($arms[$arm]));
        /** @var array<0|1, ValueExpressionResult> $narrowed */
        $narrowed = [];

        if ($proof !== null) {
            $result = $this->resolveNarrowed($proof[0], $proof[1], $arms[$proof[2]], $scope, fn (): array => $resolveArm($proof[2]));

            // `$this->resource` read whole under its own proof is the proven class; one with no published type leaves
            // the whole expression unknown, since dropping it would publish the other arm as if the test never passed.
            if ($result !== null
                && $result['type'] === 'unknown'
                && $this->isProxyFetch($arms[$proof[2]], $scope)
                && $this->isSameReadPath($proof[0], $arms[$proof[2]])
            ) {
                return $result;
            }

            if ($result !== null) {
                $narrowed[$proof[2]] = $result;
            }
        }

        if ($narrowed === [] && $nonNull === [[], []]) {
            return ValueResult::withEnumArmShapes(
                ValueResult::analyzeClosureUnion($arms, $engine, $scope, firstIsChecked: $expr->if === null),
                static fn (): array => array_map($engine->resolve(...), $arms),
            );
        }

        $results = [$narrowed[0] ?? $resolveArm(0), $narrowed[1] ?? $resolveArm(1)];

        if ($expr->if === null) {
            $results[0] = ValueResult::checkedOperand($results[0]);
        }

        // This path resolves its arms itself, so it records its own drops: analyzeClosureUnion() never sees them.
        $dropped = [];

        foreach ($results as $index => $armResult) {
            if ($armResult['type'] === 'unknown') {
                DroppedUnionArms::record($arms[$index], $scope, 'ternary-narrowed');
                $dropped[] = $arms[$index];
            }
        }

        // The narrowed results, not a second resolution of the arms: that would drop the narrowing and let two
        // resolutions of the same arm disagree by construction.
        $union = DroppedUnionArms::warnOfPlaceholder(ValueResult::unionResults($results), $dropped, $scope);

        return ValueResult::withEnumArmShapes($union, $results);
    }

    /**
     * The checked operand of a short ternary over a built API resource, where Inertia reads it unfiltered and it types.
     *
     * An object is always true and Inertia sends it as it is, so the other arm never runs; filter() can send a resource
     * around null as null, and an operand the engine cannot type leaves the other arm as the only reading.
     *
     * @return ValueExpressionResult|null
     */
    private function resourceOperand(Ternary $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr->if !== null
            || $engine->reading() !== Reading::Inertia
            || ! $this->buildsResource($expr->cond, $scope)
        ) {
            return null;
        }

        $operand = $engine->resolve($expr->cond);

        return array_diff(ValueResult::filledArms($operand['type']), ['unknown']) === []
            ? null
            : $operand;
    }

    /**
     * Whether an expression builds an API resource: `new X(…)`, or `X::make(…)` or `X::collection(…)` on one.
     */
    private function buildsResource(Expr $expr, AnalysisScope $scope): bool
    {
        $class = match (true) {
            $expr instanceof New_ => $expr->class,
            $expr instanceof StaticCall && ! $expr->isFirstClassCallable() && $expr->name instanceof Identifier
                && in_array($expr->name->toString(), ['make', 'collection'], true) => $expr->class,
            default => null,
        };

        return $class instanceof Name && is_a($this->scopeClassName($class, $scope), JsonResource::class, true);
    }
}
