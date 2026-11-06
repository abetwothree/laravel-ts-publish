<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without the interface whose get() returns a string and whose serialize() writes it inside an `array`.
 *
 * @implements CastsAttributes<string, string>
 */
final class StringArraySerializeCast implements CastsAttributes
{
    /**
     * The stored text, empty for a NULL.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : '';
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
     * The text as Model::toArray() writes it, inside an array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['v' => $value];
    }
}
