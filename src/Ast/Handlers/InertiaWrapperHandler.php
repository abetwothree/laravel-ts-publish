<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

/**
 * Inertia's prop factories — `Inertia::defer()`, `optional()`, `always()`, `merge()`, `deepMerge()`, `scroll()`,
 * `once()` — and a `new` of each one's prop class resolve to the type of the value they wrap. Only `IgnoreFirstLoad`
 * props are always absent from the initial response; a deferred scroll or an already-loaded once is runtime state.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class InertiaWrapperHandler implements ExpressionHandler, KeepsHeldObjects
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

    /** Each prop class by the factory that returns one, so `new DeferProp(…)` reads as `Inertia::defer(…)`. */
    private const PROP_CLASSES = [
        'Inertia\\DeferProp' => 'defer',
        'Inertia\\OptionalProp' => 'optional',
        'Inertia\\LazyProp' => 'lazy',
        'Inertia\\AlwaysProp' => 'always',
        'Inertia\\MergeProp' => 'merge',
        'Inertia\\ScrollProp' => 'scroll',
        'Inertia\\OnceProp' => 'once',
    ];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, New_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ((! $expr instanceof StaticCall && ! $expr instanceof New_) || $expr->isFirstClassCallable()) {
            return null;
        }

        $factory = $this->factory($expr);
        $wrapped = $factory === null ? null : $this->wrapperArguments($expr, $factory)->at(0);

        if ($wrapped === null) {
            return null;
        }

        $result = ValueResult::heldObject($engine->resolve($wrapped->value));

        return in_array($factory, self::OPTIONAL_WRAPPERS, true) ? ValueResult::missable($result) : $result;
    }

    /**
     * The factory an `Inertia::` call names, or the one that returns the prop class a `new` builds.
     */
    private function factory(StaticCall|New_ $expr): ?string
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        if ($expr instanceof New_) {
            return self::PROP_CLASSES[$expr->class->toString()] ?? null;
        }

        return $expr->class->getLast() === 'Inertia'
            && $expr->name instanceof Identifier
            && in_array($expr->name->toString(), self::WRAPPERS, true)
                ? $expr->name->toString()
                : null;
    }

    /**
     * The wrapper's arguments mapped against Inertia's own factory or constructor signature. `lazy` (gone in v3) and an
     * app without the adapter fall back to positions only. The adapter is a dev dependency, so it is named
     * by string rather than imported — an import would declare a hard requirement this package lacks.
     */
    private function wrapperArguments(StaticCall|New_ $expr, string $factory): CallArguments
    {
        [$class, $method] = $expr instanceof New_ && $expr->class instanceof Name
            ? [$expr->class->toString(), '__construct']
            : ['Inertia\\ResponseFactory', $factory];

        if (class_exists($class) && method_exists($class, $method)) {
            return CallArguments::for($expr, new ReflectionMethod($class, $method));
        }

        return CallArguments::fromNames($expr->getArgs(), []);
    }
}
