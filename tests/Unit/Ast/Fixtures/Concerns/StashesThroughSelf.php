<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns;

/** Passes a default to `self::stash()`, which names the class that uses this trait. */
trait StashesThroughSelf
{
    public $traitStashed = ['a'];

    /** Stash the trait's own default through the using class's stash(). */
    public function stashAll(): void
    {
        self::stash($this->traitStashed);
    }
}
