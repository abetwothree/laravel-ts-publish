<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A test-only collection of BuiltFactsItemResource that preserves keys, whose `toArray()` reads `summary()` on an
 * instance of itself it builds as `self` around its own items.
 */
class BuiltFactsBaseCollection extends ResourceCollection
{
    public $collects = BuiltFactsItemResource::class;

    public $preserveKeys = true;

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
