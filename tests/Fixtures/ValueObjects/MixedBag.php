<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A value object whose getters read a map of mixed values with a null fallback, beside a typed one. */
final class MixedBag
{
    /** @var array<string, mixed> */
    public array $values = ['x' => 1];

    /** One value the map may hold, untyped. */
    public function pick()
    {
        return $this->values['x'] ?? null;
    }

    /** One value the map may hold, declared mixed. */
    public function pickMixed(): mixed
    {
        return $this->values['x'] ?? null;
    }

    /** One value the map may hold, or a literal null. */
    public function pickOrNull(bool $none)
    {
        return $none ? null : 'x';
    }
}
