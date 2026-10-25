<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BaseWrittenTierResource's `toArray()` that writes `$tier` whatever it is passed. */
class ParameterTierResource extends BaseWrittenTierResource
{
    /** Move the tier to the one passed. */
    public function promote(mixed $tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
