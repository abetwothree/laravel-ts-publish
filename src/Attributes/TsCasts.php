<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify the custom TypeScript types for a model's casts.
 *
 * Can be applied on the model class itself, the $casts property, or the casts() method.
 *
 * This allows you to override the default type inference for specific attributes on a per-attribute basis, directly in your Eloquent model.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class TsCasts
{
    /** @param array<string, string> $types */
    public function __construct(public array $types) {}
}
