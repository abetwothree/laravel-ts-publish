<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\BumpsTier;

/** A test-only runtime class of WrittenTallyBase's `toArray()` that overrides the `bump()` its trait declares. */
final class OverriddenBumpChild extends WrittenTallyBase
{
    use BumpsTier;

    /** Move the tier to silver. */
    public function bump(): void
    {
        $this->tier = 'silver';
    }
}
