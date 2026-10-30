<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
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
     * Resolve a `map()` callback's body with its first parameter bound to the element: its model, or the concrete model
     * the parameter declares where the element model is not one; else its value. Null for a callable that is no
     * closure, or a variadic first parameter.
     *
     * $arrayed says JsonResource::filter() reaches the result as an array.
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

        $declared = $this->declaredMapModel($first, $scope);
        $model = $elementModel !== null && ($declared === null || is_a($elementModel, $declared, true)) ? $elementModel : $declared;
        $name = AnalysisScope::firstParameterName($callback);

        return $scope->readClaimedClosure(
            $callback,
            null,
            $engine,
            fn (): array => $engine->resolve($callback),
            function () use ($scope, $name, $model, $elementValue): void {
                if ($name !== null && $model !== null) {
                    $scope->varModelBindings[$name] = $model;
                } elseif ($name !== null && $elementValue !== null) {
                    $scope->varValueBindings[$name] = $elementValue;
                }
            },
            function () use ($scope, $arrayed): void {
                $scope->filtersNestedResources = $scope->filtersNestedResources && $arrayed;
                $scope->mapResultArrayed = false;
            },
        );
    }

    /**
     * The concrete model a map callback's first parameter declares, which PHP holds every element to; null for an
     * abstract one, such as `Model`, which names no shape of its own.
     *
     * @return class-string<Model>|null
     */
    protected function declaredMapModel(?Param $param, AnalysisScope $scope): ?string
    {
        $declared = $param === null ? null : ParameterBinder::declaredClass($param, $scope);

        return $declared !== null && ! $declared[1] && ReceiverType::isConcreteModel($declared[0]) ? $declared[0] : null;
    }
}
