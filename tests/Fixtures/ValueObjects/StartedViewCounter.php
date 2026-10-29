<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only holder whose untyped `$views` its own method writes through `static::`, which a subclass redeclares. */
class StartedViewCounter
{
    public const START = 'a';

    public $views = 'x';

    /** The count, read in a body a receiver's call reaches. */
    public function getViews()
    {
        return $this->views;
    }

    /** The object's class's start constant, read in a body a receiver's call reaches. */
    public function startConst()
    {
        return static::START;
    }

    /** Restart the count at the constant the object's class declares. */
    public function bump(): void
    {
        $this->views = static::START;
    }
}
