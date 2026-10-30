<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/**
 * A test-only collection whose name picks no class, so Laravel keeps the items it is given, whose `toArray()` reads
 * `summary()` on an instance of itself it builds as `self` around its own items.
 */
class BuiltFactsRawBase extends BuiltFactsItemCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['summary' => new self($this->resource)->summary()];
    }
}
