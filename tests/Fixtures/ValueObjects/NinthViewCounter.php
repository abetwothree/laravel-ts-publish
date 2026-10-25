<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only subclass whose `START` is an int, so its inherited `bump()` writes `$views` an int. */
class NinthViewCounter extends StartedViewCounter
{
    public const START = 9;
}
