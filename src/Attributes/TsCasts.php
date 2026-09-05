<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify custom TypeScript types for generated properties.
 *
 * Supported generators read it from their documented class, property, or method locations.
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
