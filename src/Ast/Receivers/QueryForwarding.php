<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Where a model, a relation or an Eloquent builder forwards a call it does not declare: a class fact both receiver
 * resolvers read, the class resolver for what the call returns as a receiver, the method-return resolver for its type.
 *
 * @internal
 */
final class QueryForwarding
{
    /** The calls SoftDeletingScope adds to each query of a soft-deleting model, each handing the query back. */
    private const array SOFT_DELETE_QUERIES = ['withTrashed', 'withoutTrashed', 'onlyTrashed'];

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

        return self::passesThrough($class, $methodName) && method_exists(QueryBuilder::class, $methodName) ? QueryBuilder::class : null;
    }

    /**
     * The Eloquent builder class a model's queries are, as `Model::newEloquentBuilder()` picks it: the one an override
     * of it declares, else the one a `#[UseEloquentBuilder]` on the model, a parent or a trait names, else `$builder`.
     *
     * @param  class-string<Model>  $model
     * @return class-string<Builder<Model>>
     */
    public static function builderClass(string $model): string
    {
        if (new ReflectionMethod($model, 'newEloquentBuilder')->getDeclaringClass()->getName() !== Model::class) {
            $declared = DeclaredClasses::returnClasses($model, 'newEloquentBuilder');

            return $declared !== null && count($declared) === 1 && is_subclass_of($declared[0], Builder::class) ? $declared[0] : Builder::class;
        }

        $attribute = 'Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder';
        $named = class_exists($attribute) ? self::attributedBuilder($model, $attribute) : null;
        $builder = $named ?? new ReflectionProperty($model, 'builder')->getDefaultValue();

        return is_string($builder) && is_subclass_of($builder, Builder::class) ? $builder : Builder::class;
    }

    /**
     * Whether a static call on a model runs on a new query of it, as `Model::query()->m()` would.
     *
     * `Model::__callStatic()` sends an attribute scope to `query()` and any other name to a new instance, whose
     * `__call()` forwards a method the model lacks to a new query, unless the name is `through` and a relation's.
     */
    public static function queriesStatically(string $model, string $methodName): bool
    {
        if (! ReceiverType::isConcreteModel($model)) {
            return false;
        }

        if (method_exists($model, $methodName)) {
            return self::isAttributeScope($model, $methodName);
        }

        return ! str_starts_with($methodName, 'through') || ! method_exists($model, lcfirst(substr($methodName, 7)));
    }

    /**
     * The method a model's named scope runs, found as `Model::hasNamedScope()` finds it, or null.
     *
     * @param  class-string<Model>  $model
     */
    public static function namedScope(string $model, string $methodName): ?ReflectionMethod
    {
        $prefixed = 'scope'.ucfirst($methodName);

        return match (true) {
            method_exists($model, $prefixed) => new ReflectionMethod($model, $prefixed),
            self::isAttributeScope($model, $methodName) => new ReflectionMethod($model, $methodName),
            default => null,
        };
    }

    /**
     * Whether `Builder::__call()` runs a call on the base query and hands the Eloquent builder back: a name the
     * builder lacks, with no macro or model scope under it, that its `$passthru` does not send on for a result, and
     * that the base query runs as a public method, a macro or a dynamic `where{Column}()`.
     *
     * @param  class-string<Model>  $model  the model the builder queries
     */
    public static function forwardsToBaseQuery(string $class, string $methodName, string $model): bool
    {
        return is_a($class, Builder::class, true)
            && ! method_exists($class, $methodName)
            && ! Builder::hasGlobalMacro($methodName)
            && ! self::isSoftDeleteQuery($model, $methodName)
            && self::namedScope($model, $methodName) === null
            && ! self::passesThrough($class, $methodName)
            && (QueryBuilder::hasMacro($methodName)
                || str_starts_with($methodName, 'where')
                || (method_exists(QueryBuilder::class, $methodName) && new ReflectionMethod(QueryBuilder::class, $methodName)->isPublic()));
    }

    /**
     * Whether a call is one SoftDeletingScope adds to each query of a soft-deleting model, which hands the query back.
     *
     * @param  class-string<Model>  $model
     */
    public static function isSoftDeleteQuery(string $model, string $methodName): bool
    {
        return in_array($methodName, self::SOFT_DELETE_QUERIES, true) && in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * The builder class a `#[UseEloquentBuilder]` on a model, a parent or one of their traits names, found as
     * `Model::resolveClassAttribute()` finds it; null where none carries one.
     *
     * @param  class-string<Model>  $model
     */
    private static function attributedBuilder(string $model, string $attribute): mixed
    {
        for ($class = new ReflectionClass($model); $class !== false; $class = $class->getParentClass()) {
            foreach ([$class, ...$class->getTraits()] as $holder) {
                $found = $holder->getAttributes($attribute)[0] ?? null;

                if ($found !== null) {
                    return $found->getArguments()[0] ?? $found->getArguments()['builderClass'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Whether a model method carries the `#[Scope]` attribute, read by Laravel's own check where the release has one.
     */
    private static function isAttributeScope(string $model, string $methodName): bool
    {
        if (! method_exists($model, 'isScopeMethodWithAttribute')) {
            return false;
        }

        return new ReflectionMethod($model, 'isScopeMethodWithAttribute')->invoke(null, $methodName) === true;
    }

    /**
     * Whether an Eloquent builder's `$passthru` sends a name to the base query for the base query's result.
     *
     * @param  class-string<Builder<Model>>  $class
     */
    private static function passesThrough(string $class, string $methodName): bool
    {
        $passthru = new ReflectionProperty($class, 'passthru')->getDefaultValue();

        return is_array($passthru) && in_array(strtolower($methodName), $passthru, true);
    }
}
