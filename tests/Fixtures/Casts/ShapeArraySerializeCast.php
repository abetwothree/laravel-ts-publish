<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without the interface whose get() declares a shape and whose serialize() declares only `array`.
 *
 * @implements CastsAttributes<array{a: int}, string>
 */
final class ShapeArraySerializeCast implements CastsAttributes
{
    /**
     * Reads every value, a NULL too, as the shape.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{a: int}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['a' => 1];
    }

    /**
     * Stores nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return null;
    }

    /**
     * The shape as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array
    {
        return is_array($value) ? $value : [];
    }
}
