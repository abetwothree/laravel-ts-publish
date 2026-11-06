<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing, and whose untyped get() returns a literal null for a NULL
 * and otherwise falls back to a property the constructor writes.
 *
 * @implements CastsAttributes<string|null, string>
 */
final class PropertyFallbackGetCast implements CastsAttributes, SerializesCastableAttributes
{
    /** The value the fallback copies. */
    private $base;

    /** The value a non-string reads as. */
    private $fallback;

    /**
     * Derive the fallback from a base value.
     */
    public function __construct()
    {
        $this->base = strtoupper('b');
        $this->fallback = $this->base;
    }

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

        return is_string($value) ? $value : $this->fallback;
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
    public function serialize(Model $model, string $key, mixed $value, array $attributes)
    {
        return 'v'.(is_string($value) ? $value : '');
    }
}
