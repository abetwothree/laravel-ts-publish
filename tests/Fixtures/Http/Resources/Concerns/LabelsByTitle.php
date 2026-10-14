<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns;

/** A helper with no declaration, whose `$this->title` is the title of whatever model the using resource wraps. */
trait LabelsByTitle
{
    /** The wrapped model's title. */
    public function label()
    {
        return $this->title;
    }
}
