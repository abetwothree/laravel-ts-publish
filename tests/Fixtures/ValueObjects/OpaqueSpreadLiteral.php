<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads its parent's `nestedLiteral()` through `parent::`. */
final class OpaqueSpreadLiteral extends OpaqueSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::nestedLiteral()];
    }
}
