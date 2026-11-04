<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;

/**
 * The one way a `map()` callback's parameters are bound, shared by RelationCollectionChainHandler,
 * CollectionPipelineHandler, VariableHandler and ModelFinderHandler. The body resolves through ClosureHandler, which
 * seeds its locals and binds the class any other parameter declares.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait BindsMapCallbacks
{
    /**
     * Resolve a `map()` callback's body with its first parameter bound to the element as elementBinding() binds it,
     * and its second to $keyValue where the caller passes one. Null for a callable that is no closure, or a variadic
     * first parameter.
     *
     * The answer is each element the callback returns, as the reading sends it in an array, apart from the callback.
     *
     * $arrayed says JsonResource::filter() reaches the result as an array.
     *
     * @param  class-string<Model>|null  $elementModel
     * @param  ValueExpressionResult|null  $elementValue
     * @param  ValueExpressionResult|null  $keyValue
     * @return ValueExpressionResult|null
     */
    protected function resolveMapCallback(
        ?Expr $callback,
        ?string $elementModel,
        ?array $elementValue,
        bool $arrayed,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?array $keyValue = null,
    ): ?array {
        if (! $callback instanceof ArrowFunction && ! $callback instanceof ClosureExpr) {
            return null;
        }

        $first = $callback->params[0] ?? null;

        if ($first !== null && $first->variadic) {
            return null;
        }

        $name = AnalysisScope::firstParameterName($callback);
        $binding = ParameterBinder::elementBinding($elementModel, $elementValue, $first, $scope);

        $returned = $scope->readClaimedClosure(
            $callback,
            null,
            $engine,
            fn (): array => $engine->resolve($callback),
            function () use ($callback, $scope, $engine, $name, $binding, $keyValue): void {
                if ($name !== null && $binding !== null) {
                    resolve(ParameterBinder::class)->bindPassed([$name => $binding], $scope);
                }

                if ($keyValue !== null) {
                    ParameterBinder::bindCallbackArgument($callback, 1, $keyValue, $scope, $engine);
                }
            },
            function () use ($scope, $arrayed): void {
                $scope->filtersNestedResources = $scope->filtersNestedResources && $arrayed;
                $scope->mapResultArrayed = false;
            },
        );

        return ValueResult::withoutHeldObject($engine->reading()->arrayValue(ValueResult::calledValue($returned)));
    }
}
