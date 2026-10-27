<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of LateMethodBaseResource's `toArray()`, declaring the part its parent's helper joins.
 */
class LateMethodResource extends LateMethodBaseResource
{
    /** @return array<string, mixed> */
    public function latePart(): array
    {
        return ['c' => true];
    }
}
