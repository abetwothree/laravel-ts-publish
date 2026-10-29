<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A test-only runtime class of CapturedWriteBase's `toArray()` that writes two defaults through `??` from closures: one
 * over a by-reference capture, one over an untyped parameter.
 */
final class CoalescedCaptureChild extends CapturedWriteBase
{
    /** Keep a by-reference capture, or a fallback, after a later write replaces it before the closure runs. */
    public function coalesceCapture(): static
    {
        $w = 7;
        $keep = function () use (&$w): void {
            $this->used = $w ?? 'z';
        };
        $w = true;
        $keep();

        return $this;
    }

    /** Keep the value a closure's untyped parameter is handed, or a fallback. */
    public function coalesceParameter(): static
    {
        array_map(function ($n): void {
            $this->loose = $n ?? 'z';
        }, [true]);

        return $this;
    }
}
