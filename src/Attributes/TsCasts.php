<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify custom TypeScript types for generated properties.
 *
 * Read from a model, resource, form request, or broadcast event class — including its `$casts` property or
 * `casts()` method — and from an analyzed method: a resource's `toArray()`, an Inertia controller action or
 * `HandleInertiaRequests::share()`, and a model metadata provider's `provide()`.
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
