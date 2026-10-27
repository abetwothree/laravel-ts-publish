<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\WritesTallyTier;

/**
 * A test-only runtime class of WrittenTallyBase's `toArray()` that runs a trait's `setTier()` only under an alias, and
 * overrides the name with a method whose parameter the trait's body never sees.
 */
final class AliasedTallyChild extends WrittenTallyBase
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
