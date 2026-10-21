<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use ReflectionClass;

/**
 * The one way a `map()` callback's first parameter is bound, shared by RelationCollectionChainHandler,
 * CollectionPipelineHandler and VariableHandler. The body resolves through ClosureHandler, which seeds its locals and
 * binds the class any other parameter declares.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait BindsMapCallbacks
{
    /**
     * Resolve a `map()` callback's body with its first parameter bound to the element: its model, or the concrete model
     * the parameter declares where the element model is not one; else its value. Null for a callable that is no
     * closure, or a variadic first parameter.
     *
     * $arrayed says JsonResource::filter() reaches the result as an array. $relationClosure says whether the body is a
     * relation closure, as a relation chain's map, a whenLoaded() collection's or a variable's with a model hint is, or
     * not one; null leaves the enclosing closure's answer in place.
     *
     * @param  class-string<Model>|null  $elementModel
     * @param  ValueExpressionResult|null  $elementValue
     * @return ValueExpressionResult|null
     */
    protected function resolveMapCallback(
        ?Expr $callback,
        ?string $elementModel,
        ?array $elementValue,
        bool $arrayed,
        ?bool $relationClosure,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        if (! $callback instanceof ArrowFunction && ! $callback instanceof ClosureExpr) {
            return null;
        }

        $first = $callback->params[0] ?? null;

        if ($first !== null && $first->variadic) {
            return null;
        }

        $declared = $this->declaredMapModel($first);
        $model = $elementModel !== null && ($declared === null || is_a($elementModel, $declared, true)) ? $elementModel : $declared;
        $name = $first !== null && $first->var instanceof Variable && is_string($first->var->name) ? $first->var->name : null;
        $previousNameBindings = $scope->nameBindings();
        $previousRelationModel = $scope->closureRelationModelClass;
        $filters = $scope->filtersNestedResources;
        $outerArrayed = $scope->mapResultArrayed;

        try {
            $scope->filtersNestedResources = $filters && $arrayed;
            $scope->mapResultArrayed = false;

            // Only a relation closure opens ResolvesEnumPropertyArgTypes's gate, so a map that is not one closes it.
            if ($relationClosure !== null) {
                $scope->closureRelationModelClass = $relationClosure ? $model : null;
            }

            $scope->claimParameters($callback);

            if ($name !== null && $model !== null) {
                $scope->varModelBindings[$name] = $model;
            } elseif ($name !== null && $elementValue !== null) {
                $scope->varValueBindings[$name] = $elementValue;
            }

            return $engine->resolve($callback);
        } finally {
            $scope->restoreNameBindings($previousNameBindings);
            $scope->closureRelationModelClass = $previousRelationModel;
            $scope->filtersNestedResources = $filters;
            $scope->mapResultArrayed = $outerArrayed;
        }
    }

    /**
     * The concrete model a map callback's first parameter declares, which PHP holds every element to; null for an
     * abstract one, such as `Model`, which names no shape of its own.
     *
     * @return class-string<Model>|null
     */
    protected function declaredMapModel(?Param $param): ?string
    {
        $type = $param?->type;
        $class = $type instanceof Name ? $type->toString() : null;

        return $class !== null && is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract()
            ? $class
            : null;
    }
}
