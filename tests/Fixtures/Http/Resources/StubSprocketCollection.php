<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects `StubSprocketResource` by its name, and reads `$this->resource` in its own `toArray()`. */
class StubSprocketCollection extends ResourceCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['page' => $this->resource, 'first' => $this->resource->first()];
    }
}
