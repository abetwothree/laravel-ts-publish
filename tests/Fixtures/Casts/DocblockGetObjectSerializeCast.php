<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose untyped get() documents a number, which it never leaves null, and whose serialize()
 * declares an object.
 *
 * @implements CastsAttributes<int, string>
 */
final class DocblockGetObjectSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a number, a NULL as zero.
     *
     * @param  array<string, mixed>  $attributes
     * @return int
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return is_numeric($value) ? (int) $value : 0;
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
     * The number wrapped as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): object
    {
        return (object) ['v' => $value];
    }
}
