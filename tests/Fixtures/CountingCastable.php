<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Workbench\App\Casts\CoordinateCast;

/** A Castable that counts its own calls, so a test can prove publishing never runs castUsing(). */
final class CountingCastable implements Castable
{
    public static int $calls = 0;

    /**
     * Counts the call, then names the caster by class name.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        self::$calls++;

        return CoordinateCast::class;
    }
}
