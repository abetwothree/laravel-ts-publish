<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload that reads `m()` as a value, then spreads its parent's `nestedHelper()`, which reads it too. */
final class OverwrittenSpreadMemoized extends OverwrittenSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['x' => $this->m(), ...parent::nestedHelper()];
    }
}
