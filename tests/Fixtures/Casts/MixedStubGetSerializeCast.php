<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PlotPoint;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster shaped like Laravel's `make:cast` stub, whose get() declares `mixed` and never returns null.
 *
 * @implements CastsAttributes<PlotPoint, string>
 */
final class MixedStubGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a point, a NULL as the origin.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return new PlotPoint(is_numeric($value) ? (float) $value : 0, 1);
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The point's x as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof PlotPoint ? 'p'.$value->x : '';
    }
}
