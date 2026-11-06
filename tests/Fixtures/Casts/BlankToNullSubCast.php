<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrappedLabel;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** The caster a Castable declaring WrappedLabelBaseCast returns, adding a serialize() that writes a blank as null. */
final class BlankToNullSubCast extends WrappedLabelBaseCast implements SerializesCastableAttributes
{
    /**
     * The label's text, or null for a blank one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof WrappedLabel && $value->text !== '' ? $value->text : null;
    }
}
