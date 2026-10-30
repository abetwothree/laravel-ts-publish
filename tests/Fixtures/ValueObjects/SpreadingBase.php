<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads `extra()` through `parent::`, `self::` and `static::`. */
class SpreadingBase extends SpreadingGrand
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::extra(), ...self::extra(), ...static::extra(), ...parent::sextra()];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['b' => true];
    }
}
