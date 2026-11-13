<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Arrays a body spreads by value: one of named keys and one map. */
final class GapModeValueSource
{
    /**
     * Named keys a spread writes in place.
     *
     * @return array{named: int}
     */
    public function pairs(): array
    {
        return ['named' => 1];
    }

    /**
     * A map a spread writes over the keys before it.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return ['any' => 1];
    }
}
