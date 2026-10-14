<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Models\Post;

/**
 * A collection of PageItemResource whose keys read what `$this->resource` holds: a collection of the items, or the
 * paginator it was given. The paginator-only keys throw on a plain collection.
 *
 * @mixin Post
 */
class PageItemCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'page' => $this->resource,
            'page_array' => $this->resource->toArray(),
            'page_json' => $this->resource->jsonSerialize(),
            'count' => $this->resource->count(),
            'first' => $this->resource->first(),
            'last' => $this->resource->last(),
            'is_empty' => $this->resource->isEmpty(),
            'total' => $this->resource->total(),
            'per_page' => $this->resource->perPage(),
            'current_page' => $this->resource->currentPage(),
            'last_page' => $this->resource->lastPage(),
            'has_more_pages' => $this->resource->hasMorePages(),
            'next_page_url' => $this->resource->nextPageUrl(),
            'items' => $this->resource->items(),
            'inner' => $this->resource->getCollection(),
            'mapped' => $this->resource->map(fn ($item) => $item->id),
            'only' => $this->resource->only(['id']),
            'except' => $this->resource->except(['id']),
            'rows' => $this->rows(),
        ];
    }

    /** What `$this->resource` holds, returned by a helper with no declaration. */
    private function rows()
    {
        return $this->resource;
    }
}
