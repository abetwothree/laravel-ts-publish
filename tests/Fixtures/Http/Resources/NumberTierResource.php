<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BaseWrittenTierResource's `toArray()` that writes `$tier` an int literal. */
class NumberTierResource extends BaseWrittenTierResource
{
    /** Move the tier to a number. */
    public function promote(): static
    {
        $this->tier = 5;

        return $this;
    }
}
