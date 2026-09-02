<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use Inertia\ResponseFactory;
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

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof StaticCall
            || ! $expr->class instanceof Name
            || $expr->class->getLast() !== 'Inertia'
            || ! $expr->name instanceof Identifier
            || ! in_array($expr->name->toString(), self::WRAPPERS, true)
            || $expr->isFirstClassCallable()) {
            return null;
        }

        $wrapped = $this->wrapperArguments($expr, $expr->name->toString())->at(0);

        if ($wrapped === null) {
            return null;
        }

        $result = $engine->resolve($wrapped->value);

        return [
            ...$result,
            'optional' => $result['optional'] || in_array($expr->name->toString(), self::OPTIONAL_WRAPPERS, true),
        ];
    }

    /**
     * The wrapper's arguments mapped against Inertia's own factory signature. `lazy` (gone in v3) and an
     * app without the adapter fall back to positions only; the adapter is a dev dependency, hence the guard.
     */
    private function wrapperArguments(StaticCall $expr, string $method): CallArguments
    {
        if (class_exists(ResponseFactory::class) && method_exists(ResponseFactory::class, $method)) {
            return CallArguments::for($expr, new ReflectionMethod(ResponseFactory::class, $method));
        }

        return CallArguments::fromNames($expr->getArgs(), []);
    }
}
