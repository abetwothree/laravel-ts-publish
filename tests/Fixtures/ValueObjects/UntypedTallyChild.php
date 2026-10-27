<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of WrittenTallyBase's `toArray()` that writes `$tier` the untyped value it is passed. */
final class UntypedTallyChild extends WrittenTallyBase
{
    /** Move the tier to the one passed. */
    public function setTier($tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
