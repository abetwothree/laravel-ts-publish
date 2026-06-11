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
 *
 * Each entry can be a plain type string or an array with 'type', 'import', and/or 'optional' keys:
 *
 * ```php
 * #[TsCasts([
 *     'metadata' => 'Record<string, unknown>',
 *     'dimensions' => ['type' => 'ProductDimensions', 'import' => '@js/types/product'],
 *     'deleted_at' => ['type' => 'string | null', 'optional' => true],
 * ])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class TsCasts
{
    /** @param array<string, string|array{type: string, import?: string, optional?: bool}> $types */
    public function __construct(public array $types) {}
}
