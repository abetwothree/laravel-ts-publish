<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** One arm of PostSourcePicker's union, whose value is a number. */
class PostScoreSource
{
    /** The score. */
    public function value(): int
    {
        return 1;
    }
}
