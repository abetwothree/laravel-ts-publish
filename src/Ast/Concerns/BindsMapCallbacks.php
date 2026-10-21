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
     * Resolve a `map()` callback's body with its first parameter bound to the element: the model it declares, else the
     * element model, else the element's value; $arrayed says JsonResource::filter() reaches the result as an array.
     * Null for a callable that is no closure, or a variadic first parameter, which collects the value and its key.
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

        $model = $this->declaredMapModel($first) ?? $elementModel;
        $name = $first !== null && $first->var instanceof Variable && is_string($first->var->name) ? $first->var->name : null;
        $previousNameBindings = $scope->nameBindings();
        $previousRelationModel = $scope->closureRelationModelClass;
        $filters = $scope->filtersNestedResources;
        $outerArrayed = $scope->mapResultArrayed;

        try {
            $scope->filtersNestedResources = $filters && $arrayed;
            $scope->mapResultArrayed = false;
            // The body reads the element, so an enclosing relation's model is no guess for a variable in it.
            $scope->closureRelationModelClass = $model;
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
     * The model a map callback's first parameter declares, which PHP holds every element to.
     *
     * @return class-string<Model>|null
     */
    protected function declaredMapModel(?Param $param): ?string
    {
        $type = $param?->type;

        if (! $type instanceof Name) {
            return null;
        }

        $class = $type->toString();

        return class_exists($class) && is_a($class, Model::class, true) ? $class : null;
    }
}
