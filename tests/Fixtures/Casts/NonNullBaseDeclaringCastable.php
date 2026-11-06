<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable declaring a non-null base caster, returning a subclass whose serialize() may write null. */
final class NonNullBaseDeclaringCastable implements Castable
{
    /**
     * The subclass that writes a blank label as null.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): WrappedLabelBaseCast
    {
        return new BlankToNullSubCast;
    }
}
