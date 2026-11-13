<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares a string, and whose untyped get() returns a literal null for a NULL
 * and otherwise passes the value through a recursive helper.
 *
 * @implements CastsAttributes<string|null, string>
 */
final class RecursiveHelperTypedGetCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored string, null for a NULL.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return $this->norm($value, 3);
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
        return 't'.(is_string($value) ? $value : '');
    }

    /**
     * The value once the given number of steps have passed it on.
     */
    private function norm($value, $depth)
    {
        return $depth > 0 ? $this->norm($value, $depth - 1) : (is_string($value) ? $value : '');
    }
}
