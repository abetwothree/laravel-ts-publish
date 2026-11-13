<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose get() returns a number and whose serialize() returns a string, without the interface; Model::toArray()
 * writes the string.
 *
 * @implements CastsAttributes<int, int>
 */
final class NoInterfaceTypedSerializeCast implements CastsAttributes
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
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return (int) $value;
    }

    /**
     * The number as Model::toArray() writes it, prefixed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return 'n'.$value;
    }
}
