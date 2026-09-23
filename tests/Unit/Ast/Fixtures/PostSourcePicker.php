<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Picks a source whose value is a number or a string. */
final class PostSourcePicker
{
    /** Either source, so a read before any guard holds either value. */
    public function pick(): PostScoreSource|PostLabelSource
    {
        return random_int(0, 1) === 1 ? new PostScoreSource : new PostLabelSource;
    }
}
