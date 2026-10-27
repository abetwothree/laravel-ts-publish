<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A test-only runtime class of EnclosedWriteBase's `toArray()` that writes two defaults from a callback whose parameter
 * shadows its method's, and one from a local its method retypes.
 */
final class EnclosedWriteChild extends EnclosedWriteBase
{
    /** Track the last number a closure is handed, under the name of this method's string parameter. */
    public function track(string $x): static
    {
        array_map(function (int $x): void {
            $this->last = $x;
        }, [1, 2]);

        return $this;
    }

    /** Track the last number an arrow function is handed, under the name of this method's string parameter. */
    public function trackArrow(string $x): static
    {
        array_map(fn (int $x): int => $this->arrowed = $x, [3, 4]);

        return $this;
    }

    /** Keep the length of the value passed. */
    public function viaLocal(string $v): static
    {
        $v = strlen($v);
        $this->local = $v;

        return $this;
    }
}
