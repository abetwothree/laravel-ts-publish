<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** Writes an untyped `$tier` through `self::`, which names the class that uses this trait, not the trait. */
trait PromotesTier
{
    /** Raise the tier to the constant the using class declares. */
    public function promote(): void
    {
        $this->tier = self::PROMOTED;
    }
}
