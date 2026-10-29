<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only root whose `decorate()` adds a `grand` key to the array it is passed. */
class DecoratingGrand
{
    /**
     * Adds the `grand` key to the array.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function decorate(array $d): array
    {
        $d['grand'] = 1;

        return $d;
    }
}
