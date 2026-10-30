<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root whose `m()` spreads the `m2()` CyclingSpreadBase overrides to spread `m()` again. */
class CyclingSpreadGrand
{
    /** @return array<string, mixed> */
    protected function m(): array
    {
        return [...static::m2(), 'g' => 1];
    }

    /** @return array<string, mixed> */
    protected function m2(): array
    {
        return [];
    }
}
