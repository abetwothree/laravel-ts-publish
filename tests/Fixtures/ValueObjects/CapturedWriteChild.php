<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only CapturedWriteBase runtime class writing from closures, beside an anonymous object setting its own. */
final class CapturedWriteChild extends CapturedWriteBase
{
    /** Keep a local the closure captures by value. */
    public function capture(): static
    {
        $v = 7;
        array_map(function () use ($v): void {
            $this->used = $v;
        }, [1]);

        return $this;
    }

    /** Set an anonymous object's own `$anon`, which leaves this object's untouched. */
    public function anonymous(): static
    {
        $other = new class
        {
            public $anon = 0;

            /** Set the anonymous object's own property. */
            public function set(): void
            {
                $this->anon = 1;
            }
        };
        $other->set();

        return $this;
    }

    /** Keep the value a closure's untyped parameter is handed. */
    public function loosen(): static
    {
        array_map(function ($x): void {
            $this->loose = $x;
        }, [1]);

        return $this;
    }
}
