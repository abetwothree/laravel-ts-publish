<?php

declare(strict_types=1);

namespace Workbench\App\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * A time of day cast through WallClockTime, whose `get()` is nullable and whose `serialize()` sends the stored string
 * (GH-154).
 */
class WallClock implements Castable
{
    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public static function castUsing(array $arguments): WallClockTime
    {
        return new WallClockTime;
    }
}
