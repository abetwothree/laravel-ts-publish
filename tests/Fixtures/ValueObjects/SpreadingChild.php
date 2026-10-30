<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload that runs SpreadingBase's `toArray()` with an `extra()` of its own. */
final class SpreadingChild extends SpreadingBase
{
    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['c' => 'x'];
    }
}
