<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A second value object an API resource holds, with a member HeldStats lacks. */
final class FlaggedStats
{
    public function __construct(public bool $flag = true, public int $total = 2) {}
}
