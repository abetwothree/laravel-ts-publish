<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use LogicException;
use Workbench\App\Casts\CoordinateCast;

/** A Castable whose declared return names its caster, and whose body must never run while publishing. */
final class DeclaredCastable implements Castable
{
    /**
     * Throws, so only the declared return can name the caster.
     *
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): CoordinateCast
    {
        throw new LogicException('castUsing() ran while publishing.');
    }
}
