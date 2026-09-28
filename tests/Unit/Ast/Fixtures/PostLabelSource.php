<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** The other arm of PostSourcePicker's union, whose value is a string. */
class PostLabelSource
{
    /** The label. */
    public function value(): string
    {
        return 'draft';
    }
}
