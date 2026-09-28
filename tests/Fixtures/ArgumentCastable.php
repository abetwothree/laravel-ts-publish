<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Workbench\App\Casts\CoordinateCast;

/** A Castable whose caster depends on its cast arguments, which only a call could know. */
final class ArgumentCastable implements Castable
{
    /**
     * Names the caster its first argument names, else CoordinateCast.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return is_string($arguments[0] ?? null) ? $arguments[0] : CoordinateCast::class;
    }
}
