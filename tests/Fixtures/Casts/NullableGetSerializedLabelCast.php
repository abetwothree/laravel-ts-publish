<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\StringableLabel;

/**
 * A serializing caster whose get() returns null for a NULL or empty value, which Model::toArray() sends without
 * calling serialize(), and whose serialize() declares a string.
 *
 * @implements CastsAttributes<StringableLabel|null, string>
 */
final class NullableGetSerializedLabelCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a label, null for a NULL or the empty string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StringableLabel
    {
        return is_string($value) && $value !== '' ? new StringableLabel($value) : null;
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The label as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof StringableLabel ? $value->__toString() : '';
    }
}
