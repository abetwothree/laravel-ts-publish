<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedLabel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster without SerializesCastableAttributes: get() returns a label, which a read off the model sends,
 * and serialize() writes its text, which Model::toArray() sends.
 *
 * @implements CastsAttributes<WrappedLabel, string>
 */
final class LabelMethodSerializeCast implements CastsAttributes
{
    /**
     * The stored value as a label.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): WrappedLabel
    {
        return new WrappedLabel(is_string($value) ? $value : '');
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
     * The label's text, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof WrappedLabel ? $value->text : '';
    }
}
