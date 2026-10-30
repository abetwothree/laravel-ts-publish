<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait writing an untyped `$tier` through `self::`, which names the class that uses this trait. */
trait LevelsTier
{
    /** Move the tier to the using class's LEVEL. */
    public function level(): void
    {
        $this->tier = self::LEVEL;
    }
}
