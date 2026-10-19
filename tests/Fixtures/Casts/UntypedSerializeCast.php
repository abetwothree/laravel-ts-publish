<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose serialize() declares no type at all, so only its get() says what the attribute holds.
 *
 * @implements CastsAttributes<int, int>
 */
final class UntypedSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The number as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return $value;
    }
}
