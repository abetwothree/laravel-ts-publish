<?php

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to specify the custom TypeScript type for any class, such as an Eloquent custom cast class, an enum, or a plain class, or any other class that needs to be represented as a specific TypeScript type in the generated definitions.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TsType
{
    public function __construct(public string $type) {}
}
