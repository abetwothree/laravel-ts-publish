<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** The concrete entry an `AbstractEntry&HasLabel` declaration holds at runtime. */
final class LabeledEntry extends AbstractEntry implements HasLabel
{
    /** The title, prefixed. */
    public function label(): string
    {
        return 'L:'.$this->title;
    }
}
