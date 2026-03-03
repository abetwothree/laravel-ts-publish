<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify that an enum static method should be included when generating TypeScript types
 *
 * If included, it will run the static method and the return value will be included as a property on the generated TypeScript enum object.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class TsEnumStaticMethod
{
    public function __construct(
        public string $name = '',
        public string $description = '',
    ) {}
}
