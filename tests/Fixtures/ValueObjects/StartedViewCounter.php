<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only holder whose untyped `$views` its own method writes through `static::`, which a subclass redeclares. */
class StartedViewCounter
{
    public const START = 'a';

    public $views = 'x';

    /** Restart the count at the constant the object's class declares. */
    public function bump(): void
    {
        $this->views = static::START;
    }
}
