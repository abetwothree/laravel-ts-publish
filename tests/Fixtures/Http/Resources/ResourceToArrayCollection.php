<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Returns `$this->resource->toArray()`. */
class ResourceToArrayCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
