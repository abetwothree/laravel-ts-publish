<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * This attribute can applied to an enum class to specify the TypeScrip enum name.
 *
 * This is in case you have multiple enums in different namespaces with the same name
 * that cause collisions in the generated TypeScript definitions.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TsEnum
{
    public function __construct(
        public string $name,
        public string $description = '',
    ) {}
}
