<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root whose `merged()`, `picked()` and `firstOnly()` return arrays a spread reads only partly. */
class PartlyReadSpreadBase
{
    public bool $on = true;

    /** @return array<string, mixed> */
    protected function merged(): array
    {
        return array_merge(['a' => 1], ['b' => 2]);
    }

    /** @return array<string, mixed> */
    protected function picked(): array
    {
        return $this->on ? ['a' => 1] : ['a' => 'x'];
    }

    /** @return array<string, mixed> */
    protected function firstOnly(): array
    {
        if ($this->on) {
            return ['a' => 1];
        }

        return array_merge(['a' => 'x'], ['c' => true]);
    }
}
