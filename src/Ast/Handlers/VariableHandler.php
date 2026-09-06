<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\AnalyzesPluckCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesMapProxyElementModels;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

/**
 * Expressions rooted at a bound variable rather than `$this` — `$item->name`, `$items->map(…)`,
 * `$items->pluck('x')`, `$item->method()`, and the bare variable itself resolved through the
 * scope's model, collection, closure-parameter and local-assignment binding maps.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class VariableHandler implements ExpressionHandler
{
    use AnalyzesPluckCalls;
    use InspectsAstNodes;
    use ResolvesMapProxyElementModels;
    use ResolvesModelRelationTypes;
    use ResolvesRelatedModelTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, MethodCall::class, Variable::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // $variable->property — resolve against the variable's own bound model (whenLoaded param,
        // chain map param, foreach value var), falling back to the ambient whenLoaded closure model.
        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            /** @var class-string<Model>|null $boundModel */
            $boundModel = $scope->varModelBindings[$expr->var->name] ?? $scope->closureRelationModelClass;

            if ($boundModel !== null) {
                return $this->analyzeRelatedModelProperty($expr->name->toString(), $scope, $boundModel);
            }
        }

        // `$variable->map(fn (TypedClass $item) => [...])` — no closureRelationModelClass is required
        // here, since the element type comes from the closure's own type hint.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'map'
            && ! $this->mapArguments($expr)->isEmpty()
        ) {
            $mapResult = $this->analyzeVariableMapCall($expr, $scope, $engine);

            if ($mapResult !== null) {
                return $mapResult;
            }
        }

        // $variable->pluck('field') — resolve to an array of the field's type
        if ($scope->closureRelationModelClass !== null
            && $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'pluck'
        ) {
            return $this->analyzeVariablePluckCall($expr, $scope);
        }

        // $variable->method() — resolve against the variable's own bound model, falling back to the
        // ambient whenLoaded closure model.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            /** @var class-string<Model>|null $boundModel */
            $boundModel = $scope->varModelBindings[$expr->var->name] ?? $scope->closureRelationModelClass;

            if ($boundModel !== null) {
                return $this->analyzeRelatedModelMethodCall($expr->name->toString(), $scope, $boundModel);
            }
        }

        // Bare variable bound to a model class (whenLoaded param, chain map param, foreach value var) —
        // resolves to the model's own type. Checked before closure-param/local-var expression bindings,
        // which resolve through a *different* expression rather than naming a model directly.
        if ($expr instanceof Variable && is_string($expr->name) && isset($scope->varModelBindings[$expr->name])) {
            $modelFqcn = $scope->varModelBindings[$expr->name];

            return [
                ...ValueResult::unknown(),
                'type' => class_basename($modelFqcn),
                'optional' => false,
                'modelFqcn' => $modelFqcn,
            ];
        }

        // Bare variable bound to a whole relation collection (to-many whenLoaded param) — resolves to
        // the collection type, e.g. `User[]`, never the singular element model.
        if ($expr instanceof Variable && is_string($expr->name) && isset($scope->varCollectionBindings[$expr->name])) {
            $binding = $scope->varCollectionBindings[$expr->name];

            return [
                ...ValueResult::unknown(),
                'type' => $binding['type'],
                'optional' => false,
                'modelFqcn' => $binding['modelFqcn'],
            ];
        }

        // Bare variable bound either to a closure parameter (ConditionalMethodHandler's
        // bindClosureParamsFromCondition()) or to a top-level local assignment
        // (collectLocalVarBindings). Closure-param bindings win, being the
        // narrower scope; the re-entrancy guard makes a cyclic binding resolve as unknown.
        if ($expr instanceof Variable && is_string($expr->name)) {
            $boundExpr = $scope->closureParamExprBindings[$expr->name]
                ?? $scope->localVarBindings[$expr->name]
                ?? null;

            if ($boundExpr !== null && ! isset($scope->resolvingLocalVars[$expr->name])) {
                $scope->resolvingLocalVars[$expr->name] = true;

                try {
                    return $engine->resolve($boundExpr);
                } finally {
                    unset($scope->resolvingLocalVars[$expr->name]);
                }
            }
        }

        return null;
    }

    /**
     * Analyze `$variable->map(fn (TypedClass $item) => [...])` using the closure's typed first param
     * as the element model, wrapping the body result as `elementType[]`.
     *
     * Returns null when there's no typed Model parameter, deferring to the generic method handler.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeVariableMapCall(MethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $closureArg = $this->mapArguments($call)->named('callback')?->value;

        if ($closureArg === null) {
            return null;
        }

        if ($closureArg instanceof ArrowFunction) {
            $params = $closureArg->params;
        } elseif ($closureArg instanceof ClosureExpr) {
            $params = $closureArg->params;
        } else {
            return null;
        }

        if ($params === []) {
            return null;
        }

        $firstParam = $params[0];

        // A named class type hint (already FQCN-resolved by NameResolver) wins when present — it's
        // the more specific signal. Otherwise fall back to the receiver's own relation binding, the
        // same one ConditionalMethodHandler::analyzeWhenLoaded() already populated for a to-many param.
        $paramClass = $firstParam->type instanceof Name
            ? $firstParam->type->toString()
            : $this->resolveMapProxyElementModel($call->var, $scope);

        if ($paramClass === null || ! class_exists($paramClass) || ! is_a($paramClass, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $paramClass */
        $previousRelationModel = $scope->closureRelationModelClass;
        $scope->closureRelationModelClass = $paramClass;

        $returnExprs = $this->resolveClosureReturnExpressions($closureArg);

        $bodyResult = match (count($returnExprs)) {
            0 => null,
            1 => $engine->resolve($returnExprs[0]),
            default => ValueResult::analyzeClosureUnion($returnExprs, $engine),
        };

        $scope->closureRelationModelClass = $previousRelationModel;

        if ($bodyResult === null || $bodyResult['type'] === 'unknown') {
            return null;
        }

        // ValueResult::arrayWrapType(), not a raw '[]' suffix: a union body (e.g. a mixed AsEnum/direct-enum
        // ternary) must be parenthesized before the array suffix binds.
        $bodyResult['type'] = ValueResult::arrayWrapType($bodyResult['type']);
        $bodyResult['optional'] = false;

        return $bodyResult;
    }

    /**
     * A `$variable->map(...)` call's arguments mapped against Collection::map(callable $callback).
     */
    private function mapArguments(MethodCall $call): CallArguments
    {
        return CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'map'));
    }
}
