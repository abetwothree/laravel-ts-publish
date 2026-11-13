<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose untyped get() returns null for a NULL or empty value through a ternary.
 *
 * @implements CastsAttributes<string|null, string>
 */
final class TernaryNullGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored string, null for a NULL or the empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_string($value) && $value !== '' ? $value : null;
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
        return 't'.(is_string($value) ? $value : '');
    }
}
