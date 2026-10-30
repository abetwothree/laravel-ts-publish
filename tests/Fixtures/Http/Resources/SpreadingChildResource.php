<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only resource that runs SpreadingBaseResource's `toArray()` with an `extra()` of its own. */
final class SpreadingChildResource extends SpreadingBaseResource
{
    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['ce' => 'x'];
    }
}
