<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait that keeps WritesTallyTier's `setTier()` only as `setTierNumber()`, overriding the name. */
trait AliasesTallyTier
{
    use WritesTallyTier {
        setTier as setTierNumber;
    }

    /** Leave the tier as it is. */
    public function setTier(string $tier): static
    {
        return $this;
    }
}
