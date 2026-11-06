<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A serializing caster whose untyped get() ends in a match whose every arm throws, so never returns null.
 *
 * @implements CastsAttributes<string, string>
 */
final class ThrowingMatchGetSerializeCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a prefixed string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        if (is_string($value)) {
            return 'm'.$value;
        }

        match (true) {
            $value === null => throw new LogicException('A NULL title.'),
            default => throw new LogicException('Not a string.'),
        };
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
     * The value prefixed again, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return 's'.(is_string($value) ? $value : '');
    }
}
