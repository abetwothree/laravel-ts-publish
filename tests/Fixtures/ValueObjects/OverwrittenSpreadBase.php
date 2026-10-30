<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use ArrayObject;

/** A test-only payload whose helpers overwrite `a` by spreading a function, a parent method or a chained call. */
class OverwrittenSpreadBase extends OverwrittenSpreadGrand
{
    /** @return array<string, mixed> */
    protected function m(): array
    {
        return ['a' => 1, ...array_merge(['a' => 'x'], [])];
    }

    /** @return array<string, mixed> */
    protected function viaParent(): array
    {
        return ['a' => 1, ...parent::g()];
    }

    /** @return array<string, mixed> */
    protected function chained(): array
    {
        return ['a' => 1, ...new ArrayObject(['a' => 'x'])->getArrayCopy()];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return [...parent::toArray(), 'e' => 1];
    }
}
