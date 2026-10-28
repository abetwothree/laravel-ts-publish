<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Sets the TypeScript type that every value of a class publishes as, wherever the package meets one.
 *
 * Subclasses inherit it, as do classes using a trait or implementing an interface that carries one, so a contract is
 * declared once. A class reached by two or more publishes `{Basename}Type`, one interface extending them all, where
 * each can be extended; otherwise, and for a class the package publishes itself, such as a model, the nearest wins.
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
