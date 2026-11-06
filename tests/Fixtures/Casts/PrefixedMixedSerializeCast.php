<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose get() returns a number and whose serialize(), declaring `mixed`, writes a string.
 *
 * @implements CastsAttributes<int, int>
 */
final class PrefixedMixedSerializeCast implements CastsAttributes, SerializesCastableAttributes
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
    public function serialize(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return 'n'.(is_int($value) ? $value : 0);
    }
}
