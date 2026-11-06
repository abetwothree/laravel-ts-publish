<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose untyped get() documents `mixed` and returns null for a NULL through a ternary arm.
 *
 * @implements CastsAttributes<string|null, string>
 */
final class MixedDocNullGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a string, null for a NULL.
     *
     * @param  array<string, mixed>  $attributes
     * @return mixed
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return $value === null ? null : (is_scalar($value) ? (string) $value : '');
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
        return 'n'.(is_string($value) ? $value : '');
    }
}
