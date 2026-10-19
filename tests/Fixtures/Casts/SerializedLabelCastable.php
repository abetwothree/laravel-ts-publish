<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable whose caster serializes, so Model::toArray() writes serialize()'s string in place of the value. */
final class SerializedLabelCastable implements Castable
{
    /**
     * Names the caster by class name.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return SerializedLabelCast::class;
    }
}
