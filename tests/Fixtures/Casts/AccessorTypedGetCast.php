<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AccessorWarehouse;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares a string, and whose untyped get() may read another model's accessor,
 * which publishes a null its nullable column carries in, though it never returns one.
 *
 * @implements CastsAttributes<string, string>
 */
final class AccessorTypedGetCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored string, or another warehouse's phone for any other value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_string($value) ? $value : (new AccessorWarehouse)->setRawAttributes(['name' => 'q'])->phone;
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
