<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root declaring an `extra()`, a static `sextra()` and a private `hidden()` to spread. */
class SpreadingGrand
{
    /** @return array<string, mixed> */
    protected static function sextra(): array
    {
        return ['sg' => 1];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['g' => 1];
    }

    /** @return array<string, mixed> */
    private function hidden(): array
    {
        return ['h' => 1];
    }
}
