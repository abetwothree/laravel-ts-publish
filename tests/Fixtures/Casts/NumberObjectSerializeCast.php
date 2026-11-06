<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without the interface whose get() returns a number and whose serialize() writes it inside an `object`.
 *
 * @implements CastsAttributes<int, string>
 */
final class NumberObjectSerializeCast implements CastsAttributes
{
    /**
     * The stored number, zero for a NULL.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int
    {
        return (int) $value;
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
     * The number as Model::toArray() writes it, inside an object.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): object
    {
        return (object) ['v' => $value];
    }
}
