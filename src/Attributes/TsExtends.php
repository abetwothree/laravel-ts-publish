<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify that a published TypeScript interface should extend a custom interface.
 *
 * Can be applied on model or resource classes, their parent classes, or traits used by them.
 *
 * Repeatable — use multiple attributes for multiple extends.
 *
 * ```php
 * #[TsExtends('HasTimestamps', '@/types/common')]
 * #[TsExtends('Pick<Auditable, "created_by">', '@/types/audit', ['Auditable'])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class TsExtends
{
    /**
     * @param  string  $extends  The raw TypeScript extends clause (e.g. 'CustomInterface' or 'Pick<CustomInterface, "id">').
     * @param  string|null  $import  Import path specifies the import path for the types used in the extends clause.
     * @param  list<string>|null  $types  Explicitly lists type names to import (auto-extracted from simple extends when null).
     */
    public function __construct(
        public string $extends,
        public ?string $import = null,
        public ?array $types = null,
    ) {}
}
