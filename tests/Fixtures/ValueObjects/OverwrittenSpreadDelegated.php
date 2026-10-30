<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads its parent's `extra()`, which spreads `parent::toArray()`. */
final class OverwrittenSpreadDelegated extends OverwrittenSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::extra()];
    }
}
