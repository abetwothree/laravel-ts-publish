<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins;

/** An invokable registered as a resource macro; Macroable calls it unbound. */
class TicketMacro
{
    /** The `ticket` macro's value. */
    public function __invoke(): float
    {
        return 1.5;
    }
}
