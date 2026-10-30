<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root whose helpers spread `opaque()`, a ternary no arm reads, in a nested array or a value. */
class OpaqueSpreadBase
{
    /** @return array<string, mixed> */
    protected function nestedLiteral(): array
    {
        return ['w' => ['o' => 1, ...$this->opaque()]];
    }

    /** @return array<string, mixed> */
    protected function nestedHelper(): array
    {
        return ['v' => $this->spreadsOpaque()];
    }

    /** @return array<string, mixed> */
    protected function spreadsOpaque(): array
    {
        return ['o' => 1, ...$this->opaque()];
    }

    /** @return array<string, mixed> */
    protected function opaque(): array
    {
        return func_get_args() === [] ? ['o' => 'x'] : [];
    }
}
