<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\StringableLabel;

/**
 * A cast whose get() returns a `__toString()` value object, which Model::toArray() writes as serialize()'s string.
 *
 * @implements CastsAttributes<StringableLabel, string>
 */
final class SerializedLabelCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * The stored value as a label.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): StringableLabel
    {
        return new StringableLabel(is_string($value) ? $value : '');
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
        return is_string($value) ? $value : '';
    }
}
