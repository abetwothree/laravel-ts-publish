<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable that is not the value; the caster it builds returns the stored value from an untyped get(). */
final class OpaqueGetCastable implements Castable
{
    /**
     * The caster that returns the stored value and writes it prefixed.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): OpaqueGetSerializeCast
    {
        return new OpaqueGetSerializeCast;
    }
}
