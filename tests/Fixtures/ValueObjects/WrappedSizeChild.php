<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of MeasuredSizeBase's `toArray()` whose write nests what its helper reads back. */
final class WrappedSizeChild extends MeasuredSizeBase
{
    /** Wrap the size the helper measures, one level deeper each call. */
    public function wrap(): static
    {
        $this->size = ['v' => $this->measure()];

        return $this;
    }
}
