<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\PostCollection;

/**
 * A test-only collection whose name picks no class under PostCollection, so Laravel keeps the items it is given, whose
 * `toArray()` reads `summary()` on an instance of itself it builds as `self` around its own items.
 */
class BuiltFactsPostsBase extends PostCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['summary' => new self($this->resource)->summary()];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return ['items' => $this->collection];
    }
}
