<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * The casters a model cast runs, read without calling a `Castable`'s `castUsing()`, since it is application code.
 *
 * @internal
 */
final class CastCasters
{
    /**
     * The classes a cast runs: a caster class itself, or the casters a `Castable`'s `castUsing()` returns, which are
     * what Laravel builds, else the ones it declares; null when it names none, as Laravel's own anonymous casters do.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function casters(string $cast): ?array
    {
        if (! is_a(Str::before($cast, ':'), Castable::class, true)) {
            return self::declared($cast);
        }

        $returned = self::returned($cast);

        if ($returned !== null && array_all($returned, self::isCaster(...))) {
            return $returned;
        }

        $declared = self::declared($cast);

        // A declared `CastsAttributes` interface names no caster, so the returns stand.
        return $declared !== null && array_all($declared, self::isCaster(...)) ? $declared : $returned;
    }

    /**
     * The classes a cast names without reading a body: a caster class itself, or what a `Castable`'s `castUsing()`
     * declares it returns, which may be a base class the caster it builds extends.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function declared(string $cast): ?array
    {
        $class = Str::before($cast, ':');

        if (is_a($class, Castable::class, true)) {
            return DeclaredClasses::returnClasses($class, 'castUsing');
        }

        return self::isCaster($class) ? [$class] : null;
    }

    /**
     * The classes a `Castable`'s `castUsing()` returns, read from its body; null for any other cast.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function returned(string $cast): ?array
    {
        $class = Str::before($cast, ':');

        return is_a($class, Castable::class, true)
            ? resolve(ReceiverClassResolver::class)->bodyReturnClasses($class, 'castUsing')
            : null;
    }

    /**
     * Whether what every caster sends declares a native type that excludes null: its `get()`, and its `serialize()`
     * too where it has one, which Model::toArray() writes. Laravel never calls an inbound caster's `get()`.
     */
    public static function getDeclaresNonNull(string $cast): bool
    {
        $casters = self::casters($cast);

        return $casters !== null && array_all($casters, self::sendsNonNull(...));
    }

    /**
     * Whether a caster's `get()`, and its `serialize()` where it has one, declare a native non-null return, and it is
     * not inbound too. Laravel looks for the method, not the `SerializesCastableAttributes` interface.
     */
    private static function sendsNonNull(string $caster): bool
    {
        return is_a($caster, CastsAttributes::class, true)
            && ! is_a($caster, CastsInboundAttributes::class, true)
            && self::returnsNonNull($caster, 'get')
            && (! method_exists($caster, 'serialize') || self::returnsNonNull($caster, 'serialize'));
    }

    /**
     * Whether a method declares a native return type that excludes null.
     */
    private static function returnsNonNull(string $class, string $method): bool
    {
        $return = new ReflectionMethod($class, $method)->getReturnType();

        return $return !== null && ! $return->allowsNull();
    }

    /**
     * Whether a class is a caster Laravel can build: one casting both ways or inbound only, neither abstract nor an
     * interface.
     *
     * @phpstan-assert-if-true class-string $class
     */
    private static function isCaster(string $class): bool
    {
        return (is_a($class, CastsAttributes::class, true) || is_a($class, CastsInboundAttributes::class, true))
            && new ReflectionClass($class)->isInstantiable();
    }
}
