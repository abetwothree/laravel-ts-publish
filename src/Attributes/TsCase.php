<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Attributes;

use Attribute;

/**
 * Attribute to customize an enum case when generating TypeScript types
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class TsCase
{
    public function __construct(
        public string $name = '',
        public string|int $value = '',
        public string $description = '',
    ) {}
}
