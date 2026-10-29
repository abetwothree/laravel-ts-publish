<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of MeasuredSizeBase's `toArray()` whose writes read the size back through its helper. */
final class MeasuredSizeChild extends MeasuredSizeBase
{
    /** Move the size to the length of what the helper measures. */
    public function resize(): static
    {
        $this->size = strlen((string) $this->measure());

        return $this;
    }

    /** Keep the size the helper measures, which reads the size it writes. */
    public function keep(): static
    {
        $this->size = $this->measure();

        return $this;
    }
}
