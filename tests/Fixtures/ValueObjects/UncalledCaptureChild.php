<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only CapturedWriteBase runtime class whose closure, writing a typed parameter, is never called. */
final class UncalledCaptureChild extends CapturedWriteBase
{
    /** Build a closure that would keep the number it is handed, and leave it uncalled. */
    public function park(): static
    {
        $keep = function (int $n): void {
            $this->used = $n;
        };

        return $this;
    }
}
