<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A parent whose `self` returns name itself, while `static` returns name whichever class it is read through. */
class ReceiverBaseDto
{
    /** A native `self` return. */
    public function copy(): self
    {
        return new self;
    }

    /** A native `static` return. */
    public function fresh(): static
    {
        return $this;
    }

    /**
     * A docblock-only `self` return.
     *
     * @return self
     */
    public function docCopy()
    {
        return new self;
    }

    /**
     * A docblock-only `static` return.
     *
     * @return static
     */
    public function docFresh()
    {
        return $this;
    }
}
