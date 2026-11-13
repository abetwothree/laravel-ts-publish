<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root whose `toArray()` spreads a decoded array over `a`, and whose `g()` returns a merge. */
class OverwrittenSpreadGrand
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['a' => 1, ...(array) json_decode('{"a":"x"}', true)];
    }

    /** @return array<string, mixed> */
    protected function g(): array
    {
        return array_merge(['a' => 'x'], []);
    }
}
