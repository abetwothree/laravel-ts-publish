<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait whose `bump()` writes the untyped `$tier` of the class that uses it a number. */
trait BumpsTier
{
    /** Move the tier to five. */
    public function bump(): void
    {
        $this->tier = 5;
    }
}
