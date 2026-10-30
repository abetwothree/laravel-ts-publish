<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A test-only collection that collects BuiltFactsItemResource by its name, whose `toArray()` reads `summary()` on an
 * instance of itself it builds as `self` around its own items.
 */
class BuiltFactsItemCollection extends ResourceCollection
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
