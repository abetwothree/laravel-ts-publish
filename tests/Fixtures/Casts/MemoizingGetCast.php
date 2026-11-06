<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A serializing caster whose serialize() declares nothing, and whose untyped get() returns a literal null for a NULL
 * and otherwise memoizes the value through `??=`, which the engine cannot read.
 *
 * @implements CastsAttributes<string|null, string>
 */
final class MemoizingGetCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * Each value already read, by its string.
     *
     * @var array<string, string>
     */
    private array $seen = [];

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

        return $this->seen[(string) $value] ??= strtoupper((string) $value);
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
        return 'w'.(is_string($value) ? $value : '');
    }
}
