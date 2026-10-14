<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use ReflectionClass;
use ReflectionMethod;

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
     * Resolve a subject method call by its declaration, else its body read with what the call passes.
     *
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null null when nothing in scope declares the method, or declares it
     *                                    with a return type the acceptor rejects, so the handler
     *                                    declines and dispatch reaches the next claimant
     */
    public function resolve(AnalysisScope $scope, string $methodName, ?CallSite $site = null): ?array
    {
        $own = $this->resolveOn($scope->subjectReflection, $methodName, $site);

        if ($own !== null) {
            return $own;
        }

        return $this->resolveForwarded($scope, $methodName, $site);
    }

    /**
     * What a call forwarded to the wrapped class, else the model the proxy holds, returns; the model answers when the
     * wrapped class declares the method but cannot type it. A bare `$this->m()`, `$this::m()`,
     * `$this->resource::m()` and `$this->resource->m()` all read this.
     *
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null
     */
    public function resolveForwarded(AnalysisScope $scope, string $methodName, ?CallSite $site = null): ?array
    {
        $wrappedClass = $this->resolveWrappedClass($scope);

        foreach ([$wrappedClass, $scope->forwardsUndeclaredMembersTo] as $class) {
            if ($class === null) {
                continue;
            }

            $accepted = $this->forwardedOn($class, $methodName, $scope, $site);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        return null;
    }

    /**
     * What a call forwarded to one class returns: the rules' answer for a model method they type, else the
     * declaration-then-body read. A non-public method declines, as PHP sends it to `__call`.
     *
     * @param  class-string  $class
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null
     */
    public function forwardedOn(string $class, string $methodName, AnalysisScope $scope, ?CallSite $site = null): ?array
    {
        if (! method_exists($class, $methodName) || ! new ReflectionMethod($class, $methodName)->isPublic()) {
            return null;
        }

        $receiverRules = resolve(ReceiverMethodReturnResolver::class);

        return $receiverRules->typesModelMethod($class, $methodName)
            ? $receiverRules->resolve(ReceiverType::of($class), $methodName, $scope)
            : resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName, $site);
    }

    /**
     * Reflect one class's own declaration of a method, or null when it declares none the acceptor
     * will take. Split out so a caller holding a class rather than a scope — a controller's
     * `$this->service->method()` — reflects it by exactly the same rules.
     *
     * @param  ReflectionClass<object>  $reflection
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null
     */
    public function resolveOn(ReflectionClass $reflection, string $methodName, ?CallSite $site = null): ?array
    {
        if (! $reflection->hasMethod($methodName)) {
            return null;
        }

        /** @var class-string $class */
        $class = $reflection->getName();

        return resolve(MethodReturnTypeResolver::class)->resolve($class, $methodName, $site);
    }
}
