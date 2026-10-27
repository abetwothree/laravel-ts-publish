<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A `static` factory, which `self::` forwards the calling class to, beside a `new self`, which builds this class. */
class ForwardedFreshBase
{
    public static function fresh(): static
    {
        return new static;
    }

    /** @return array<string, mixed> */
    public function kind(): array
    {
        return ['a' => 1];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return ['self_fresh' => self::fresh()->kind(), 'new_self' => (new self)->kind()];
    }
}
