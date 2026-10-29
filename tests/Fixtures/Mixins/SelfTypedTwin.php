<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins;

use Closure;

/** A mixin whose macro is typed `self`, which names the class Macroable binds it to, never this one. */
class SelfTypedTwin
{
    /** The `twin` macro: a copy of the object it runs on, read from a body that types nothing. */
    public function twin(): Closure
    {
        return function (): self {
            return unserialize(serialize($this));
        };
    }
}
