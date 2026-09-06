<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use PhpParser\Node\Expr;

/**
 * Tries registered handlers, in registration order, for an expression's concrete class, returning
 * the first non-null resolution. Order is load-bearing: it IS the legacy guard-chain's order.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ExpressionDispatcher
{
    /** @var array<class-string<Expr>, list<ExpressionHandler>> Per-concrete-class memo; misses cached too. */
    private array $candidatesByClass = [];

    /**
     * @param  list<ExpressionHandler>  $handlers  Registration order is dispatch order.
     */
    public function __construct(protected array $handlers) {}

    /**
     * Dispatch to the first candidate handler that resolves the expression, or null if none do.
     *
     * @return ValueExpressionResult|null
     */
    public function dispatch(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $nodeClass = $expr::class;

        // Miss caching matters as much as hit caching: a node class no handler claims must not
        // re-scan every handler's nodeClasses() on every dispatch of that class.
        $this->candidatesByClass[$nodeClass] ??= array_values(array_filter(
            $this->handlers,
            fn (ExpressionHandler $handler): bool => array_any(
                $handler->nodeClasses(),
                fn (string $claimed): bool => is_a($nodeClass, $claimed, true),
            ),
        ));

        foreach ($this->candidatesByClass[$nodeClass] as $handler) {
            $result = $handler->resolve($expr, $scope, $engine);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
