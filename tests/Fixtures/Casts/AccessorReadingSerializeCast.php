<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AccessorCycleWarehouse;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing and reads an accessor that reads this same cast back.
 *
 * @implements CastsAttributes<string, string>
 */
final class AccessorReadingSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored string, or a placeholder for any other value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : 'y';
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
     * Another warehouse's phone accessor, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return (new AccessorCycleWarehouse)->setRawAttributes(['phone' => 'p', 'name' => 'k'])->phone;
    }
}
