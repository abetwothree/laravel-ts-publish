<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\FirstClassCallableHandler;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;

/**
 * The one read of what a callback returns where its caller calls it with given values, as Laravel's value() and
 * transform() helpers call theirs.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ResolvesCallbackValues
{
    /**
     * The value a callback returns, `unknown` included, called with $passed, each bound to the parameter at its
     * position by ParameterBinder::bindCallbackArgument(), and every later one to its default. A variable reads as the
     * callable it holds, and a first-class callable as the call it stands for; any other value is read as itself.
     *
     * @param  list<Expr|ValueExpressionResult>  $passed
     * @return ValueExpressionResult
     */
    protected function callbackValue(Expr $callback, array $passed, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $callback = FirstClassCallableHandler::heldCallable($callback, $scope);
        $callback = $passed === [] ? $callback : FirstClassCallableHandler::passingOneValue($callback);

        return $scope->readClaimedClosure(
            $callback,
            count($passed),
            $engine,
            fn (): array => $engine->resolve(FirstClassCallableHandler::invokedCall($callback)),
            $passed === [] ? null : function () use ($callback, $passed, $scope, $engine): void {
                if (! $callback instanceof ArrowFunction && ! $callback instanceof ClosureExpr) {
                    return;
                }

                foreach ($passed as $position => $argument) {
                    ParameterBinder::bindCallbackArgument($callback, $position, $argument, $scope, $engine);
                }
            },
        );
    }
}
