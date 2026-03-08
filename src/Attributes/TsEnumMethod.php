<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify that an enum method should be included when generating TypeScript types
 *
 * If included, each case of the enum will be used to call the method.
 * The returned values will be included in the generated TypeScript types as an object
 * with keys corresponding to the case names and values corresponding to the method return values.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class TsEnumMethod
{
    public function __construct(
        public string $name = '',
        public string $description = '',
    ) {}
}
