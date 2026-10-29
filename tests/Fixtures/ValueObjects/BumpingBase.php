<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` reads an untyped `$tier` its own `bump()` writes a number. */
class BumpingBase
{
    protected $tier = 'gold';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['tier' => $this->tier];
    }

    /** Move the tier to five. */
    public function bump(): void
    {
        $this->tier = 5;
    }
}
