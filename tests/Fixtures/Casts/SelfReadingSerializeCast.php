<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfReadingSerializeWarehouse;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing and returns the attribute this same caster casts on another
 * model, so reading its body reads its own cast again.
 *
 * @implements CastsAttributes<string, string>
 */
final class SelfReadingSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a string, a NULL as the empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Another warehouse's phone, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return $this->warehouse()->phone;
    }

    /**
     * A new warehouse model.
     */
    private function warehouse(): SelfReadingSerializeWarehouse
    {
        return new SelfReadingSerializeWarehouse;
    }
}
