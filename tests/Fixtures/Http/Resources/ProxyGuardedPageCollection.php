<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A collection whose toArray() tests its items for a length-aware paginator, and reads them past the test too.
 *
 * @mixin ProxyCollectedPost
 */
final class ProxyGuardedPageCollection extends ResourceCollection
{
    public $collects = ProxyCollectedPostResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof LengthAwarePaginator) {
            return [
                'total' => $this->total(),
                'total_via_resource' => $this->resource->total(),
                'page_items' => $this->items(),
            ];
        }

        return ['page' => $this->resource->toArray()];
    }
}
