<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Dispatch;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\SplitsOnCondition;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArm;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
use PhpParser\Node\Expr;
use WeakMap;

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

    /** @var WeakMap<Expr, ExpressionHandler> the handler that answered each node, the only one asked for its arms */
    private WeakMap $answeredBy;

    /**
     * @param  list<ExpressionHandler>  $handlers  Registration order is dispatch order.
     */
    public function __construct(protected array $handlers)
    {
        $this->answeredBy = new WeakMap;
    }

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
            $this->answeredBy[$expr] = $handler;

            // A value a handler reads from a held object, as `when()` calls a closure, is not that object itself.
            return $handler instanceof KeepsHeldObjects ? $result : ValueResult::withoutHeldObject($result);
        }

        DroppedUnionArms::consumed($gaps, 'unknown');

        return null;
    }

    /**
     * The arms of a node from the handler that answered it in dispatch(), so each arm is typed by the code that typed
     * the node; null where that handler splits on no condition, or no handler answered.
     */
    public function conditionalArms(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?ConditionalArms
    {
        $handler = $this->answeredBy[$expr] ?? null;

        if (! $handler instanceof SplitsOnCondition) {
            return null;
        }

        if ($handler instanceof KeepsHeldObjects) {
            return $handler->conditionalArms($expr, $scope, $engine);
        }

        // As dispatch() reads such a handler: apart from a closure's returned value, and no arm is the object it reads.
        $arms = $scope->readsCallableReturn
            ? $scope->apartFromReturn(fn (): ?ConditionalArms => $handler->conditionalArms($expr, $scope, $engine))
            : $handler->conditionalArms($expr, $scope, $engine);

        return $arms === null ? null : new ConditionalArms(array_map(
            static fn (ConditionalArm $arm): ConditionalArm => $arm->result === null
                ? $arm
                : new ConditionalArm($arm->guard, $arm->body, ValueResult::withoutHeldObject($arm->result), $arm->throws),
            $arms->arms,
        ));
    }
}
