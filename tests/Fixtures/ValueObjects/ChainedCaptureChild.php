<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only CapturedWriteBase subclass writing from a closure another closure holds, which imports from that one. */
final class ChainedCaptureChild extends CapturedWriteBase
{
    /** Keep the flag both closures import in turn, and the number the inner one imports from the outer's parameter. */
    public function chain(): static
    {
        $v = true;
        $outer = function (int $n) use ($v): void {
            $inner = function () use ($v, $n): void {
                $this->used = $v;
                $this->loose = $n;
            };
            $inner();
        };
        $outer(5);

        return $this;
    }
}
