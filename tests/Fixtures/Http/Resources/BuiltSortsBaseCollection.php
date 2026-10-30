<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/**
 * A test-only collection that re-indexes numeric keys, whose `toArray()` reads `summary()` on an instance of itself it
 * builds around raw items and `sorted()` on one it builds around its own.
 */
class BuiltSortsBaseCollection extends BuiltFactsBaseCollection
{
    public $preserveKeys = false;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'raw' => new self(collect(['k' => 1]))->summary(),
            'sorted' => new self($this->resource)->sorted(),
        ];
    }

    /** @return array<string, array<int, string>> */
    public function sorted(): array
    {
        $letters = ['b', 'a'];
        asort($letters);

        return ['letters' => $letters];
    }
}
