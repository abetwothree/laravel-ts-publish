<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Workbench\App\Casts\CoordinateCast;

/** A Castable whose declared return names only the interface, so its caster is the one its body builds. */
final class InstanceCastable implements Castable
{
    /**
     * Builds the caster through a local variable.
     *
     * @param  array<mixed>  $arguments
     * @return CastsAttributes<mixed, mixed>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        $caster = new CoordinateCast;

        return $caster;
    }
}
