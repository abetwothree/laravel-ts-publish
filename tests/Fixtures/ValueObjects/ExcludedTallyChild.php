<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\NamesTallyTier;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\WritesTallyTier;

/** A test-only runtime class of WrittenTallyBase's `toArray()` that runs a trait's excluded `setTier()` as an alias. */
final class ExcludedTallyChild extends WrittenTallyBase
{
    use NamesTallyTier, WritesTallyTier {
        NamesTallyTier::setTier insteadof WritesTallyTier;
        WritesTallyTier::setTier as setTierNumber;
    }
}
