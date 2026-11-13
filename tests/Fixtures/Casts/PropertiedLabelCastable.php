<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/** A Castable that is not the value, though it has a public property; its caster's get() returns a label. */
final class PropertiedLabelCastable implements Castable
{
    public string $code = 'c';

    /**
     * The caster that reads a label and writes its text.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): LabelMethodSerializeCast
    {
        return new LabelMethodSerializeCast;
    }
}
