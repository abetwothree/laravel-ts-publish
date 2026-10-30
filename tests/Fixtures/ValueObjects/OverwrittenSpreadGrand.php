<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root whose `toArray()` spreads an `array_merge()` over `a`, and whose `g()` returns one. */
class OverwrittenSpreadGrand
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['a' => 1, ...array_merge(['a' => 'x'], [])];
    }

    /** @return array<string, mixed> */
    protected function g(): array
    {
        return array_merge(['a' => 'x'], []);
    }
}
