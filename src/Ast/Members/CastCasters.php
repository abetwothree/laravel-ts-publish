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
     * Whether every caster's `get()` declares a native type that excludes null.
     */
    public static function getDeclaresNonNull(string $cast): bool
    {
        $casters = self::casters($cast);

        return $casters !== null && array_all($casters, static function (string $caster): bool {
            if (! is_a($caster, CastsAttributes::class, true)) {
                return false;
            }

            $return = new ReflectionMethod($caster, 'get')->getReturnType();

            return $return !== null && ! $return->allowsNull();
        });
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
