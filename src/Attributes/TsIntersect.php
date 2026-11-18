<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to intersect a published API resource's type with a type you write, for a link between its keys that the
 * package cannot read from the resource's code. The resource then publishes as `export type X = { … } & (<type>)`.
 *
 * Can be applied on resource classes, their parent classes, or traits used by them.
 *
 * ```php
 * #[TsIntersect('PostPlacementVariants', '@/types/placement')]
 * #[TsIntersect('Pick<Auditable, "created_by">', '@/types/audit', ['Auditable'])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class TsIntersect
{
    /**
     * @param  string  $type  The raw TypeScript type the resource's type is intersected with.
     * @param  string|null  $import  Import path for the types the intersected type names.
     * @param  list<string>|null  $types  Explicitly lists type names to import (read from the type when null).
     */
    public function __construct(
        public string $type,
        public ?string $import = null,
        public ?array $types = null,
    ) {}
}
