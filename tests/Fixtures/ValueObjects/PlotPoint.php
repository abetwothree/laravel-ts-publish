<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PlotPointCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/** A castable point whose caster's `get()` never returns null, which Laravel calls for a NULL column too. */
final class PlotPoint implements Castable
{
    public function __construct(public float $x = 0, public float $y = 0) {}

    /**
     * Names the caster by class name.
     *
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return PlotPointCast::class;
    }
}
