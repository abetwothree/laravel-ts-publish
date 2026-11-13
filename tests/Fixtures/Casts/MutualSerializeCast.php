<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\CycleWarehouse;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing and, for an empty value, returns another warehouse's name,
 * which the same caster casts, so reading its body meets itself.
 *
 * @implements CastsAttributes<string, string>
 */
final class MutualSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a string, a NULL as `c`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : 'c';
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
     * The value prefixed, or another warehouse's name, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_string($value) && $value !== '' ? 'c'.$value : (new CycleWarehouse)->setRawAttributes(['name' => 'e'])->name;
    }
}
