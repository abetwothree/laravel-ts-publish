<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A plain value class a map parameter can be typed with. */
class MapOption
{
    public function __construct(public int $itemId = 7) {}

    /** Doubles the id. */
    public function doubled(): int
    {
        return $this->itemId * 2;
    }
}
