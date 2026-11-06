<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose untyped get() documents `int|null` and returns null for a value that is no number.
 *
 * @implements CastsAttributes<int|null, string>
 */
final class NullableDocGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored number, null for any other value.
     *
     * @param  array<string, mixed>  $attributes
     * @return int|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_numeric($value) ? (int) $value : null;
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
     * The value prefixed, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return 'd'.(is_int($value) ? $value : '');
    }
}
