<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
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
     * The classes a cast runs: a caster class itself, or the casters a `Castable`'s `castUsing()` declares, else the
     * ones its returns name; null when it names none, as Laravel's own anonymous casters do.
     *
     * @return non-empty-list<class-string>|null
     */
    public static function casters(string $cast): ?array
    {
        $class = Str::before($cast, ':');

        if (is_a($class, Castable::class, true)) {
            $declared = DeclaredClasses::returnClasses($class, 'castUsing');

            // A declared `CastsAttributes` interface names no caster, so the returns are read instead.
            return $declared !== null && array_all($declared, self::isCaster(...))
                ? $declared
                : resolve(ReceiverClassResolver::class)->bodyReturnClasses($class, 'castUsing');
        }

        return self::isCaster($class) ? [$class] : null;
    }

    /**
     * Whether what every caster sends declares a native type that excludes null: its `get()`, and a serializing
     * caster's `serialize()` too, which Model::toArray() writes. Laravel never calls an inbound caster's `get()`.
     */
    public static function getDeclaresNonNull(string $cast): bool
    {
        $casters = self::casters($cast);

        return $casters !== null && array_all($casters, self::sendsNonNull(...));
    }

    /**
     * Whether a caster's `get()`, and a serializing one's `serialize()`, declare a native non-null return, and it is
     * not inbound too.
     */
    private static function sendsNonNull(string $caster): bool
    {
        return is_a($caster, CastsAttributes::class, true)
            && ! is_a($caster, CastsInboundAttributes::class, true)
            && self::returnsNonNull($caster, 'get')
            && (! is_a($caster, SerializesCastableAttributes::class, true)
                || self::returnsNonNull($caster, 'serialize'));
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
