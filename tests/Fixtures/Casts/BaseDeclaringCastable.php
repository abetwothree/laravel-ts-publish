<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable whose castUsing() declares a base caster that names no value, and returns a serializing subclass. */
final class BaseDeclaringCastable implements Castable
{
    /**
     * The serializing subclass.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): PassThroughBaseCast
    {
        return new WrappedLabelSubCast;
    }
}
