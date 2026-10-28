<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify the custom TypeScript type for custom cast classes.
 *
 * Can receive a string directly:
 *
 * ```php
 * #[TsType('CustomType')]
 * ```
 *
 * Or an array with 'type' and optional 'import' keys for more complex types:
 *
 * ```php
 * #[TsType(['type' => 'MyType', 'import' => '@js/types'])]
 * ```
 *
 * @phpstan-type TsTypeValue = string|array{type: string, import?: string}
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TsType
{
    /**
     * @param  TsTypeValue  $type
     * @param  bool  $inherit  False to publish this override alone, without the ones it would inherit.
     */
    public function __construct(public string|array $type, public bool $inherit = true) {}
}
