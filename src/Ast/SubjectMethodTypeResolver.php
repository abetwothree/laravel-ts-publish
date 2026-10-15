<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resolve a generic `$this->method()`/`$this::method()` call by the one declaration PHP runs: the subject's own, else
 * that of the first class `$this->resource` holds that declares it, as `__call` forwards it there.
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
    /**
     * Resolve a subject method call by its declaration, else its body read with what the call passes.
     *
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null null when nothing in scope declares the method, or the declaration PHP runs
     *                                    types nothing, so the handler declines and dispatch reaches the next claimant
     */
    public function resolve(AnalysisScope $scope, string $methodName, ?CallSite $site = null): ?array
    {
        // PHP runs the subject's own declaration, so a namesake it shadows never answers for it.
        if ($scope->subjectReflection->hasMethod($methodName)) {
            return $this->resolveOn($scope->subjectReflection, $methodName, $site);
        }

        return $this->resolveForwarded($scope, $methodName, $site);
    }

    /**
     * What a call forwarded to `$this->resource` returns: the union of each ReceiverClassResolver::proxyCallTargets()
     * answer, final even when one types nothing, so a model namesake never answers for a class declaring the method.
     * A bare `$this->m()`, `$this::m()`, `$this->resource::m()` and `$this->resource->m()` all read this.
     *
     * @param  CallSite|null  $site  the call, read where it is written, for a body read with what it passes
     * @return ValueExpressionResult|null null as well for a resource collection's items, which the receiver rules type
     */
    public function resolveForwarded(AnalysisScope $scope, string $methodName, ?CallSite $site = null): ?array
    {
        $results = [];

        foreach (resolve(ReceiverClassResolver::class)->proxyCallTargets($methodName, $scope) as $class) {
            $result = $this->forwardedOn($class, $methodName, $scope, $site);

            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        // A union drops an `unknown` arm, which would publish a type one class does not return.
        return match (true) {
            $results === [] => null,
            count($results) === 1 => $results[0],
            array_any($results, fn (array $result): bool => TsTypeString::isUnknownOnly($result['type'])) => null,
            default => ValueResult::unionResults($results),
        };
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
