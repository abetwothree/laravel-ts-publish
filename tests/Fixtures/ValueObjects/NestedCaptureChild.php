<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Error;

/**
 * A test-only runtime class of CapturedWriteBase's `toArray()` that writes one default from a by-reference capture, one
 * from a static closure PHP refuses `$this`, and one from an arrow function reading its enclosing closure's parameter.
 */
final class NestedCaptureChild extends CapturedWriteBase
{
    /** Keep a local a closure captures by reference, which a later write replaces before the closure runs. */
    public function captureByReference(): static
    {
        $v = 7;
        $keep = function () use (&$v): void {
            $this->used = $v;
        };
        $v = 'late';
        $keep();

        return $this;
    }

    /** Try writing from a static closure, which has no `$this`, so the default stays. */
    public function refuse(): static
    {
        try {
            array_map(static fn (): int => $this->anon = 1, [1]);
        } catch (Error) {
        }

        return $this;
    }

    /** Keep the number an outer closure is handed, written by an arrow function inside it. */
    public function nest(): static
    {
        array_map(function (int $n): void {
            array_map(fn (): int => $this->loose = $n, [1]);
        }, [3]);

        return $this;
    }
}
