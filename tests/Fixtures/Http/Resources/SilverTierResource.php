<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BaseWrittenTierResource's `toArray()` that writes `$tier` a string literal. */
class SilverTierResource extends BaseWrittenTierResource
{
    /** Move the tier to silver. */
    public function promote(): static
    {
        $this->tier = 'silver';

        return $this;
    }
}
