<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Casts\CoordinateCast;

/**
 * Both Castable and CastsAttributes: Laravel asks castUsing() first, so the value is a Coordinate, never the string
 * this class's own get() declares.
 *
 * @implements CastsAttributes<string, string>
 */
final class ReceiverDualCast implements Castable, CastsAttributes
{
    /**
     * @param  array<mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return CoordinateCast::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return '';
    }
}
