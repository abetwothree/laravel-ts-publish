<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia;

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
 * @phpstan-type PropFactory = array{name: string, class: string, method: string}
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

        // json_encode() sends a prop object as `{}`, which the `unknown` a `new` of one read as before admits.
        $built = $expr instanceof New_;
        $factory = $built && $engine->reading()->encodes() ? null : $this->factory($expr);
        $wrapped = $factory === null ? null : $this->wrapperArguments($expr, $factory)->at(0);

        if ($wrapped === null) {
            return null;
        }

        // Inertia resolves a prop's callable value, and sends a callable that call returns as `{}`.
        $value = $scope->apartFromReturn(fn (): array => $engine->resolve($wrapped->value));
        $value = $built && ($value['returnsCallable'] ?? false) ? ValueResult::unknown() : $value;
        $result = ValueResult::heldObject($value, prop: true);

        return in_array($factory['name'], self::OPTIONAL_WRAPPERS, true) ? ValueResult::missable($result) : $result;
    }

    /**
     * The factory an `Inertia::` call names, or the one returning the prop class a `new` builds.
     *
     * With it, the method its arguments map against: the factory's own, or the class's constructor.
     *
     * @return PropFactory|null
     */
    private function factory(StaticCall|New_ $expr): ?array
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        if ($expr instanceof New_) {
            $class = $expr->class->toString();

            return isset(self::PROP_CLASSES[$class])
                ? ['name' => self::PROP_CLASSES[$class], 'class' => $class, 'method' => '__construct']
                : null;
        }

        $name = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        return $expr->class->getLast() === 'Inertia' && in_array($name, self::WRAPPERS, true)
            ? ['name' => $name, 'class' => 'Inertia\\ResponseFactory', 'method' => $name]
            : null;
    }

    /**
     * The wrapper's arguments mapped against Inertia's own factory or constructor signature. `lazy` (gone in v3) and an
     * app without the adapter fall back to positions only. The adapter is a dev dependency, so it is named
     * by string rather than imported — an import would declare a hard requirement this package lacks.
     *
     * @param  PropFactory  $factory
     */
    private function wrapperArguments(StaticCall|New_ $expr, array $factory): CallArguments
    {
        if (class_exists($factory['class']) && method_exists($factory['class'], $factory['method'])) {
            return CallArguments::for($expr, new ReflectionMethod($factory['class'], $factory['method']));
        }

        return CallArguments::fromNames($expr->getArgs(), []);
    }
}
