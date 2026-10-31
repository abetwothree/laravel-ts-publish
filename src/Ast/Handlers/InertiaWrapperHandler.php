<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

/**
 * Inertia's prop factories — `Inertia::defer()`, `optional()`, `always()`, `merge()`, `deepMerge()`,
 * `scroll()`, `once()` — resolve to the type of the value they wrap. Only `IgnoreFirstLoad` props are
 * always absent from the initial response; a deferred scroll or an already-loaded once is runtime state.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class InertiaWrapperHandler implements ExpressionHandler
{
    /**
     * Optional means `IgnoreFirstLoad`: unconditionally absent from the initial response. Conditional
     * absence — a `scroll()` the caller deferred, a `once()` the client already holds — stays required,
     * since partial reloads can drop any prop and typing those `?:` would make every prop optional.
     */
    private const OPTIONAL_WRAPPERS = ['defer', 'optional', 'lazy'];

    /**
     * Prop factories whose value is their first argument. `lazy` left v3 for `optional`, but inertia is
     * not a dependency here, so a consumer on an older adapter still calls it. `shareOnce` is excluded:
     * it takes the value second and shares the prop itself, making it a statement, not a prop expression.
     */
    private const WRAPPERS = ['defer', 'optional', 'lazy', 'always', 'merge', 'deepMerge', 'scroll', 'once'];

    /**
     * Whether an expression calls one of Inertia's prop factories, which build a prop object PHP holds until Inertia
     * resolves it.
     */
    public static function isPropFactory(Expr $expr): bool
    {
        return self::factory($expr) !== null;
    }

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $factory = self::factory($expr);

        if ($factory === null || ! $expr instanceof StaticCall || $expr->isFirstClassCallable()) {
            return null;
        }

        $wrapped = $this->wrapperArguments($expr, $factory)->at(0);

        if ($wrapped === null) {
            return null;
        }

        $result = $engine->resolve($wrapped->value);

        return in_array($factory, self::OPTIONAL_WRAPPERS, true) ? ValueResult::missable($result) : $result;
    }

    /**
     * The name of the Inertia prop factory an expression calls, or null for any other expression.
     */
    private static function factory(Expr $expr): ?string
    {
        if (! $expr instanceof StaticCall || ! $expr->class instanceof Name || $expr->class->getLast() !== 'Inertia'
            || ! $expr->name instanceof Identifier) {
            return null;
        }

        $name = $expr->name->toString();

        return in_array($name, self::WRAPPERS, true) ? $name : null;
    }

    /**
     * The wrapper's arguments mapped against Inertia's own factory signature. `lazy` (gone in v3) and an
     * app without the adapter fall back to positions only. The adapter is a dev dependency, so it is named
     * by string rather than imported — an import would declare a hard requirement this package lacks.
     */
    private function wrapperArguments(StaticCall $expr, string $method): CallArguments
    {
        $factory = 'Inertia\\ResponseFactory';

        if (class_exists($factory) && method_exists($factory, $method)) {
            return CallArguments::for($expr, new ReflectionMethod($factory, $method));
        }

        return CallArguments::fromNames($expr->getArgs(), []);
    }
}
