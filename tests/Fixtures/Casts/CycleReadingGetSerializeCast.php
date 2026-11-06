<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\CycleSerializeWarehouse;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfCycleWarehouse;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose untyped get() reads, for a value that is no string, an attribute it casts itself or one
 * whose caster's serialize() reads that attribute, so reading it meets itself.
 *
 * @implements CastsAttributes<string, string>
 */
final class CycleReadingGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored string, or another warehouse's phone or name.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_string($value) ? $value : (is_int($value)
            ? (new SelfCycleWarehouse)->setRawAttributes(['phone' => 'z'])->phone
            : (new CycleSerializeWarehouse)->setRawAttributes(['name' => 'y'])->name);
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
        return 's'.(is_string($value) ? $value : '');
    }
}
