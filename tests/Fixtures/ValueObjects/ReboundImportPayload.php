<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose untyped defaults closures write from imports they may reassign. */
final class ReboundImportPayload
{
    protected $rebound = 'none';

    protected $kept = 'none';

    protected $relayed = 'none';

    protected $arrowed = 'none';

    protected $paired = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rebound' => $this->rebound, 'kept' => $this->kept, 'relayed' => $this->relayed, 'arrowed' => $this->arrowed,
            'paired' => $this->paired,
        ];
    }

    /** Write a flag a closure imports, then reassign the import after the write. */
    public function rebind(): static
    {
        $v = true;
        $f = function () use ($v): void {
            $this->rebound = $v;
            $v = 5;
        };
        $f();

        return $this;
    }

    /** Write a flag a closure imports and never reassigns. */
    public function keep(): static
    {
        $v = true;
        $f = function () use ($v): void {
            $this->kept = $v;
        };
        $f();

        return $this;
    }

    /** Write, from a closure inside another, a flag the outer one imports and reassigns once the inner one is built. */
    public function relay(): static
    {
        $v = true;
        $outer = function () use ($v): void {
            $inner = function () use ($v): void {
                $this->relayed = $v;
            };
            $inner();
            $v = 5;
        };
        $outer();

        return $this;
    }

    /** Write, from an arrow function inside a closure, a flag the closure imports and reassigns once it has run. */
    public function arrow(): static
    {
        $v = true;
        $f = function () use ($v): void {
            array_map(fn (): bool => $this->arrowed = $v, [1]);
            $v = 5;
        };
        $f();

        return $this;
    }

    /** Write a flag a closure imports beside a count it reassigns, which the write never reads. */
    public function pair(): static
    {
        $v = true;
        $w = 1;
        $f = function () use ($v, $w): void {
            $this->paired = $v;
            $w = 5;
        };
        $f();

        return $this;
    }
}
