<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads its parent's `chained()` through `parent::`. */
final class OverwrittenSpreadChained extends OverwrittenSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::chained()];
    }
}
