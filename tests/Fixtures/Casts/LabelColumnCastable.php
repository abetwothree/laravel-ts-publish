<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable whose caster's get() returns null for a NULL or empty value and whose serialize() declares a string. */
final class LabelColumnCastable implements Castable
{
    /**
     * The caster that reads a nullable label and writes its text.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): NullableGetSerializedLabelCast
    {
        return new NullableGetSerializedLabelCast;
    }
}
