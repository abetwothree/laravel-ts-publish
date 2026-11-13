<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedLabel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without SerializesCastableAttributes whose get() returns a string or a label and whose serialize()
 * writes an array.
 *
 * @implements CastsAttributes<string|WrappedLabel, mixed>
 */
final class UnionGetArraySerializeCast implements CastsAttributes
{
    /**
     * The stored value as get() reads it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string|WrappedLabel
    {
        return is_string($value) && $value !== '' ? new WrappedLabel($value) : '';
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    /**
     * The value wrapped in an array, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['v' => $value instanceof WrappedLabel ? $value->text : $value];
    }
}
