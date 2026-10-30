<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only base whose `run()` calls a subclass's instance method through that subclass's name. */
class NamedPickRoot
{
    /** The pick of the subclass this object may be, as PHP runs `NamedPickMid::pick()` on `$this` when it is one. */
    public function run()
    {
        return NamedPickMid::pick();
    }
}
