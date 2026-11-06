<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PlotPoint;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads a stored `x,y` as a point, a NULL as the origin.
 *
 * @implements CastsAttributes<PlotPoint, string>
 */
final class PlotPointCast implements CastsAttributes
{
    /**
     * Reads the point, the origin for a NULL.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PlotPoint
    {
        [$x, $y] = array_pad(explode(',', (string) $value), 2, '0');

        return new PlotPoint((float) $x, (float) $y);
    }

    /**
     * Stores the point as `x,y`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof PlotPoint ? $value->x.','.$value->y : (string) $value;
    }
}
