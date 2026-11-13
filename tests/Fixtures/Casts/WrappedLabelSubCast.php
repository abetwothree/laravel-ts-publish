<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedLabel;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** The serializing caster a Castable declaring PassThroughBaseCast returns: a label, written as its text. */
final class WrappedLabelSubCast extends PassThroughBaseCast implements SerializesCastableAttributes
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
     * The label's text, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof WrappedLabel ? $value->text : '';
    }
}
