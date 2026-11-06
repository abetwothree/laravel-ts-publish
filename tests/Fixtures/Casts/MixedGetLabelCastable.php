<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable that is not the value; the caster it builds declares only `mixed` for get(). */
final class MixedGetLabelCastable implements Castable
{
    /**
     * The caster that reads a label and writes its text.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): MixedGetLabelCast
    {
        return new MixedGetLabelCast;
    }
}
