<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedLabel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster whose get() declares a non-null label and which has no serialize(), the base a Castable may declare.
 *
 * @implements CastsAttributes<WrappedLabel, string>
 */
class WrappedLabelBaseCast implements CastsAttributes
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
}
