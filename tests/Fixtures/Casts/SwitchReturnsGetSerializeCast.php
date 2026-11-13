<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares a string, and whose untyped get() returns from every case of a
 * switch with a default, so it never falls off its end.
 *
 * @implements CastsAttributes<string, string>
 */
final class SwitchReturnsGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value prefixed, or a placeholder.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        switch (true) {
            case is_string($value):
                return 'a'.$value;
            default:
                return 'z';
        }
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
        return 's'.(is_scalar($value) ? (string) $value : '');
    }
}
