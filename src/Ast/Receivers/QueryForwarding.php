<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Where a relation or an Eloquent builder forwards a call it does not declare: a class fact both receiver resolvers
 * read, the class resolver for what the call returns as a receiver and the method-return resolver for its type.
 *
 * @internal
 */
final class QueryForwarding
{
    /**
     * The class whose declaration answers a method a relation or an Eloquent builder does not declare, or null.
     *
     * A relation's `__call()` forwards the call to its Eloquent builder, and the builder's sends a name its `$passthru`
     * lists to the base query; a macro under the name answers before either forwards, so that call forwards nowhere.
     *
     * @return class-string|null
     */
    public static function forwardedClass(string $class, string $methodName): ?string
    {
        if (method_exists($class, $methodName)) {
            return null;
        }

        if (is_a($class, Relation::class, true)) {
            return match (true) {
                Relation::hasMacro($methodName) => null,
                method_exists(Builder::class, $methodName) && new ReflectionMethod(Builder::class, $methodName)->isPublic() => Builder::class,
                default => self::forwardedClass(Builder::class, $methodName),
            };
        }

        if (! is_a($class, Builder::class, true) || Builder::hasGlobalMacro($methodName)) {
            return null;
        }

        $passthru = new ReflectionProperty($class, 'passthru')->getDefaultValue();

        return is_array($passthru) && in_array(strtolower($methodName), $passthru, true) && method_exists(QueryBuilder::class, $methodName)
            ? QueryBuilder::class
            : null;
    }
}
