<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LiteralNullCycleProduct;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing and returns the not-null name LiteralNullCycleProduct reads
 * through LiteralNullCycleGetSerializeCast's get(), which may return null.
 *
 * @implements CastsAttributes<string, string>
 */
final class ReadsLiteralNullColumnCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a string.
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
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Another product's name, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return (new LiteralNullCycleProduct)->setRawAttributes(['name' => 'q'])->name;
    }
}
