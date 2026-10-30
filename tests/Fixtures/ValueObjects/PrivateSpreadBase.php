<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads its parent's private `hidden()` through `parent::`, which throws. */
final class PrivateSpreadBase extends SpreadingGrand
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['k' => 1, ...parent::hidden()];
    }
}
