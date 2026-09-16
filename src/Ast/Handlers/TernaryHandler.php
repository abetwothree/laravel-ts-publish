<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * Ternary and Elvis expressions — both arms resolved through the engine and unioned.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class TernaryHandler implements ExpressionHandler
{
    use InspectsAstNodes;

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
     * An `instanceof` condition narrows its subject for the true arm only.
     *
     * @return ValueExpressionResult
     */
    private function analyzeTernary(Ternary $expr, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $ifExpr = $expr->if ?? $expr->cond;

        $narrowed = $expr->if !== null && $expr->cond instanceof Instanceof_
            ? $this->narrowedArmResult($expr->cond, $ifExpr, $scope, $engine)
            : null;

        if ($narrowed === null) {
            $result = ValueResult::analyzeClosureUnion([$ifExpr, $expr->else], $engine, $scope);
        } else {
            $elseResult = $engine->resolve($expr->else);

            // This path resolves its arms itself, so it records its own drops: analyzeClosureUnion()
            // never sees them.
            if ($narrowed['type'] === 'unknown') {
                DroppedUnionArms::record($ifExpr, $scope, 'ternary-narrowed');
            }

            if ($elseResult['type'] === 'unknown') {
                DroppedUnionArms::record($expr->else, $scope, 'ternary-narrowed');
            }

            $result = ValueResult::unionResults([$narrowed, $elseResult]);
        }

        return $this->recordMixedArmShapes($result, $ifExpr, $expr->else, $engine, $narrowed);
    }

    /**
     * Resolve a ternary's true arm under what its `instanceof` condition proves, or null when it proves nothing.
     *
     * A variable subject narrows through varClassBindings; `$this->resource` narrows the scope's own
     * model, which every `$this->prop` read on a resource resolves against.
     *
     * @return ValueExpressionResult|null
     */
    private function narrowedArmResult(Instanceof_ $cond, Expr $ifExpr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $cond->class instanceof Name) {
            return null;
        }

        $class = $cond->class->toString();

        if (! class_exists($class) && ! interface_exists($class)) {
            return null;
        }

        if ($cond->expr instanceof Variable && is_string($cond->expr->name)) {
            $previousVarClassBindings = $scope->varClassBindings;
            $scope->varClassBindings[$cond->expr->name] = [$class];

            try {
                return $engine->resolve($ifExpr);
            } finally {
                $scope->varClassBindings = $previousVarClassBindings;
            }
        }

        if (! $this->isResourceFetch($cond->expr) || ! is_a($class, Model::class, true)) {
            return null;
        }

        $previousModelClass = $scope->modelClass;
        $previousForwardsTo = $scope->forwardsUndeclaredMembersTo;
        $scope->modelClass = $class;

        // Derived from the subject, never from the previous value: the live rule this replaced re-read
        // `modelClass` after the assignment, so a proxying subject forwarded here whether or not it had
        // been forwarding before — a ternary-only guard leaves instanceOfWrappedClass unseeded.
        if ($scope->subjectReflection->isSubclassOf(JsonResource::class)) {
            $scope->forwardsUndeclaredMembersTo = $class;
        }

        try {
            return $engine->resolve($ifExpr);
        } finally {
            $scope->modelClass = $previousModelClass;
            $scope->forwardsUndeclaredMembersTo = $previousForwardsTo;
        }
    }

    /**
     * A mixed union (one arm wraps via EnumResource, the other reads directly) collapses to one
     * deduped bare type name, so the merged result alone can't tell an array-shaped arm from a
     * scalar one — re-resolving each arm here, while still distinct, is the only place that survives.
     *
     * @param  ValueExpressionResult  $result
     * @param  ValueExpressionResult|null  $ifResult  the true arm already resolved under its narrowing, if any
     * @return ValueExpressionResult
     */
    private function recordMixedArmShapes(array $result, Expr $ifExpr, Expr $elseExpr, ExpressionEngine $engine, ?array $ifResult = null): array
    {
        if (! isset($result['enumFqcn'], $result['directEnumFqcn']) || $result['enumFqcn'] !== $result['directEnumFqcn']) {
            return $result;
        }

        // Reuse the narrowed resolution when there was one: resolving again here would drop the narrowing
        // and let two resolutions of the same arm disagree by construction.
        $ifResult ??= $engine->resolve($ifExpr);
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
