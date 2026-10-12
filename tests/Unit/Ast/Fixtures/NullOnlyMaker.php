<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A class that is not a resource, whose make() returns only null. */
final class NullOnlyMaker
{
    /**
     * Nothing, always.
     */
    public static function make(): null
    {
        return null;
    }
}
