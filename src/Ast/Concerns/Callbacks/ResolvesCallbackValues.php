<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use Closure;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;

/**
 * Reads what a callback returns where its caller calls it with given values, as Laravel's value() and transform()
 * helpers call theirs.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ReadArgument from ParameterBinder
 *
 * @internal
 */
trait ResolvesCallbackValues
{
    /**
     * The value a callback returns, `unknown` included, called with $passed, each bound to the parameter at its
     * position by ParameterBinder::bindCallbackArguments(), and every later one to its default. A variable reads as the
     * callable it holds, and a first-class callable as the call it stands for; any other value is read as itself.
     *
     * @param  list<Expr|ValueExpressionResult>  $passed
     * @return ValueExpressionResult
     */
    protected function callbackValue(Expr $callback, array $passed, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return $this->calledCallbackValue(
            $callback,
            count($passed),
            $scope,
            $engine,
            static function (ArrowFunction|ClosureExpr $closure) use ($passed, $scope, $engine): void {
                ParameterBinder::bindCallbackArguments($closure, $passed, $scope, $engine);
            },
        );
    }

    /**
     * The value a callback returns where its caller calls any callable, a string or pair as the call it names, and read
     * each value it passes in its own scope, so a write in the callback's body to a name that value reads changes
     * nothing (ParameterBinder::bindReadArguments()).
     *
     * @param  list<ReadArgument>  $passed
     * @return ValueExpressionResult
     */
    protected function callbackValueOfRead(Expr $callback, array $passed, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $called = CallableForms::calledCallable($callback, $scope, count($passed));

        if ($called === null) {
            return ValueResult::unknown();
        }

        return $this->calledCallbackValue(
            $called,
            count($passed),
            $scope,
            $engine,
            static function (ArrowFunction|ClosureExpr $closure) use ($passed, $scope, $engine): void {
                ParameterBinder::bindReadArguments($closure, $passed, $scope, $engine);
            },
        );
    }

    /**
     * The value a callback returns called with $passed values, its parameters bound by $bind.
     *
     * @param  Closure(ArrowFunction|ClosureExpr): void  $bind
     * @return ValueExpressionResult
     */
    private function calledCallbackValue(Expr $callback, int $passed, AnalysisScope $scope, ExpressionEngine $engine, Closure $bind): array
    {
        $callback = CallableForms::heldCallable($callback, $scope);
        $callback = $passed !== 0 && CallableForms::isFirstClassCallable($callback)
            ? CallableForms::asOneValueClosure($callback, $passed) ?? $callback
            : $callback;

        return $scope->readClaimedClosure(
            $callback,
            $passed,
            $engine,
            fn (): array => $engine->resolve(CallableForms::invokedCall($callback)),
            $passed === 0 ? null : function () use ($callback, $bind): void {
                if ($callback instanceof ArrowFunction || $callback instanceof ClosureExpr) {
                    $bind($callback);
                }
            },
        );
    }
}
