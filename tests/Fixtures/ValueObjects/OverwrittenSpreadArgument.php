<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` passes an overwriting array to its parent's `withArgument()`. */
final class OverwrittenSpreadArgument extends OverwrittenSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::withArgument(['k' => 1, ...array_merge(['k' => 'x'], [])])];
    }
}
