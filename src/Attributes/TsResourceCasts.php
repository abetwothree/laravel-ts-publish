<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify custom TypeScript types for a resource's properties.
 *
 * Applied on the resource class to override or extend the types inferred from AST analysis.
 *
 * Each entry can be a plain type string, an array with 'type' and 'import' keys,
 * or an array with 'type' and 'optional' keys:
 *
 * ```php
 * #[TsResourceCasts([
 *     'metadata' => 'Record<string, unknown>',
 *     'settings' => ['type' => 'AppSettings', 'import' => '@js/types/settings'],
 *     'secret' => ['type' => 'string', 'optional' => true],
 * ])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TsResourceCasts
{
    /** @param array<string, string|array{type: string, import?: string, optional?: bool}> $types */
    public function __construct(public array $types) {}
}
