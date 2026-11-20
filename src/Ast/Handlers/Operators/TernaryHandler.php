<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\NarrowsInstanceofSubjects;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\ReadsInstanceofChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Narrowing\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\SplitsOnCondition;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArm;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
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
 * @phpstan-import-type InstanceofProof from ReadsInstanceofChains
 *
 * @internal
 */
final class TernaryHandler implements ExpressionHandler, KeepsHeldObjects, SplitsOnCondition
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
     * The condition guarding the true arm, and nothing the false one; a short `?:` has no condition apart from its arm.
     */
    public function conditionalArms(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?ConditionalArms
    {
        if (! $expr instanceof Ternary || $expr->if === null) {
            return null;
        }

        $narrowed = $this->narrowedArm($expr, $this->instanceofProof($expr->cond), $scope, $engine);

        return new ConditionalArms([
            new ConditionalArm($expr->cond, $expr->if, $this->armResult($expr, 0, $narrowed, $scope, $engine)),
            new ConditionalArm(null, $expr->else, $this->armResult($expr, 1, $narrowed, $scope, $engine)),
        ]);
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
        $proof = $expr->if === null ? null : $this->instanceofProof($expr->cond);
        $narrowed = $this->narrowedArm($expr, $proof, $scope, $engine);

        // `$this->resource` read whole under its own proof is the proven class; one with no published type leaves
        // the whole expression unknown, since dropping it would publish the other arm as if the test never passed.
        if ($proof !== null
            && isset($narrowed[$proof[2]])
            && $narrowed[$proof[2]]['type'] === 'unknown'
            && $this->isProxyFetch($arms[$proof[2]], $scope)
            && $this->isSameReadPath($proof[0], $arms[$proof[2]])
        ) {
            return $narrowed[$proof[2]];
        }

        if ($narrowed === [] && $this->nonNullReads($expr->cond, true) === [] && $this->nonNullReads($expr->cond, false) === []) {
            return ValueResult::withEnumArmShapes(
                ValueResult::analyzeClosureUnion($arms, $engine, $scope, firstIsChecked: $expr->if === null),
                static fn (): array => array_map($engine->resolve(...), $arms),
            );
        }

        $results = [$this->armResult($expr, 0, $narrowed, $scope, $engine), $this->armResult($expr, 1, $narrowed, $scope, $engine)];

        if ($expr->if === null) {
            $results[0] = ValueResult::checkedOperand($results[0]);
        }

        // This path resolves its arms itself, so it records its own drops: analyzeClosureUnion() never sees them.
        $left = [];

        foreach ($results as $index => $armResult) {
            if ($armResult['type'] === 'unknown') {
                $left[] = [$arms[$index], 'ternary-narrowed'];
            }
        }

        // The narrowed results, not a second resolution of the arms: that would drop the narrowing and let two
        // resolutions of the same arm disagree by construction.
        $union = DroppedUnionArms::leftOut(ValueResult::unionResults($results), $left, $scope);

        return ValueResult::withEnumArmShapes($union, $results);
    }

    /**
     * The arm an `instanceof` proof holds in, read with its subject narrowed and keyed by its index; empty where there
     * is no proof or its subject cannot be narrowed.
     *
     * @param  InstanceofProof|null  $proof
     * @return array<0|1, ValueExpressionResult>
     */
    private function narrowedArm(Ternary $expr, ?array $proof, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        if ($proof === null) {
            return [];
        }

        $arm = $proof[2];
        $result = $this->resolveNarrowed($proof[0], $proof[1], $this->armExpr($expr, $arm), $scope, fn (): array => $this->armResult($expr, $arm, [], $scope, $engine));

        return $result === null ? [] : [$arm => $result];
    }

    /**
     * One arm's result: as narrowed where the condition's `instanceof` proof holds in it, else read under the reads the
     * condition proves non-null where it runs.
     *
     * @param  0|1  $arm
     * @param  array<0|1, ValueExpressionResult>  $narrowed
     * @return ValueExpressionResult
     */
    private function armResult(Ternary $expr, int $arm, array $narrowed, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return $narrowed[$arm] ?? $this->resolveProvenNonNull(
            $this->nonNullReads($expr->cond, $arm === 0),
            $scope,
            fn (): array => $engine->resolve($this->armExpr($expr, $arm)),
        );
    }

    /**
     * The expression one arm evaluates; a short `?:`'s true arm is its condition.
     *
     * @param  0|1  $arm
     */
    private function armExpr(Ternary $expr, int $arm): Expr
    {
        return $arm === 0 ? $expr->if ?? $expr->cond : $expr->else;
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
