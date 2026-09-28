<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use ReflectionClass;

/**
 * Resolve a generic `$this->method()`/`$this::method()` call by reflecting its declared return
 * type: own methods, then the wrapped class, then the backing model, to cover calls delegated via
 * `__call`/`@mixin`.
 *
 * Shared because more than one guard reflects a subject method the same way: StaticCallHandler's
 * `$this::staticMethod()` branch and RelationCollectionChainHandler's generic `$this->method()` guard.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectMethodTypeResolver
{
    use InspectsResourceSubject;

    /**
     * @return ValueExpressionResult|null null when nothing in scope declares the method, or declares it
     *                                    with a return type the acceptor rejects, so the handler
     *                                    declines and dispatch reaches the next claimant
     */
    public function resolve(AnalysisScope $scope, string $methodName): ?array
    {
        $own = $this->resolveOn($scope->subjectReflection, $methodName);

        if ($own !== null) {
            return $own;
        }

        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass !== null && method_exists($wrappedClass, $methodName)) {
            /** @var class-string $wrappedClass */
            $accepted = resolve(MethodReturnTypeResolver::class)->resolve($wrappedClass, $methodName);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        if ($scope->modelClass !== null && method_exists($scope->modelClass, $methodName)) {
            /** @var class-string $modelClass */
            $modelClass = $scope->modelClass;
            $accepted = resolve(MethodReturnTypeResolver::class)->resolve($modelClass, $methodName);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        return null;
    }

    /**
     * Reflect one class's own declaration of a method, or null when it declares none the acceptor
     * will take. Split out so a caller holding a class rather than a scope — a controller's
     * `$this->service->method()` — reflects it by exactly the same rules.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return ValueExpressionResult|null
     */
    public function resolveOn(ReflectionClass $reflection, string $methodName): ?array
    {
        if (! $reflection->hasMethod($methodName)) {
            return null;
        }

        /** @var class-string $class */
        $class = $reflection->getName();

        return resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName);
    }
}
