<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Models\Post;

/**
 * A collection that collects nothing, so Laravel keeps the raw models.
 *
 * @mixin Post
 */
class PageRawCollection extends ResourceCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'page' => $this->resource,
            'page_array' => $this->resource->toArray(),
            'first' => $this->resource->first(),
            'total' => $this->resource->total(),
            'only' => $this->resource->only(['id']),
        ];
    }
}
