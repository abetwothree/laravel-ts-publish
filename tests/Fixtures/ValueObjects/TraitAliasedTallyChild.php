<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\AliasesTallyTier;

/** A test-only runtime class of WrittenTallyBase's `toArray()` that runs a `setTier()` only as its trait's alias. */
final class TraitAliasedTallyChild extends WrittenTallyBase
{
    use AliasesTallyTier;
}
