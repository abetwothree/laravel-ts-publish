<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PlotPoint;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without the interface whose serialize() declares only `array|object`, vaguer than its get().
 *
 * @implements CastsAttributes<PlotPoint, string>
 */
final class ArrayOrObjectSerializeCast implements CastsAttributes
{
    /**
     * Reads every value, a NULL too, as a point.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PlotPoint
    {
        return new PlotPoint(1, 2);
    }

    /**
     * Stores nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return null;
    }

    /**
     * The point as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array|object
    {
        return $value instanceof PlotPoint ? ['x' => $value->x, 'y' => $value->y] : [];
    }
}
