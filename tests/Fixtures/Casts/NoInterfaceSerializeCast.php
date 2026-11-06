<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast with an untyped serialize() but not the interface, which Model::toArray() calls all the same: it writes an
 * empty string as null.
 *
 * @implements CastsAttributes<string, string>
 */
final class NoInterfaceSerializeCast implements CastsAttributes
{
    /**
     * The stored value as a string, a NULL as the empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $value;
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * The value as Model::toArray() writes it, null for the empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return $value === '' ? null : $value;
    }
}
