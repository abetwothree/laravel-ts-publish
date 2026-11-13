<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Dispatch;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use PhpParser\Node\Expr;

/**
 * Tries registered handlers, in registration order, for an expression's concrete class, returning
 * the first non-null resolution. Order is load-bearing: it IS the legacy guard-chain's order.
 *
 * A gap a handler's reads made or carried in that its resolution, or the `unknown` left where every handler declines,
 * no longer spells was consumed there, so its warnings rise, unless a merge in those reads filled it (consumed()).
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

        $gaps = DroppedUnionArms::gapMark();

        foreach ($this->candidatesByClass[$nodeClass] as $handler) {
            // What a handler keeping no object reads, as an array's item or a call's argument, is no longer the value a
            // closure returns, so Inertia calls a callable it holds.
            $result = $scope->readsCallableReturn && ! $handler instanceof KeepsHeldObjects
                ? $scope->apartFromReturn(fn (): ?array => $handler->resolve($expr, $scope, $engine))
                : $handler->resolve($expr, $scope, $engine);

            if ($result === null) {
                continue;
            }

            DroppedUnionArms::consumed($gaps, $result['type']);

            // A value a handler reads from a held object, as `when()` calls a closure, is not that object itself.
            return $handler instanceof KeepsHeldObjects ? $result : ValueResult::withoutHeldObject($result);
        }

        DroppedUnionArms::consumed($gaps, 'unknown');

        return null;
    }
}
