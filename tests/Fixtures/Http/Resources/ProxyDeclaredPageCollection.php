<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A collection that declares its items always arrive in a length-aware paginator.
 *
 * @mixin ProxyCollectedPost
 */
final class ProxyDeclaredPageCollection extends ResourceCollection
{
    /** @var LengthAwarePaginator<int, ProxyCollectedPostResource> */
    public $resource;

    public $collects = ProxyCollectedPostResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'page' => $this->resource,
            'total' => $this->total(),
            'total_via_resource' => $this->resource->total(),
            'first' => $this->first(),
            'first_via_resource' => $this->resource->first(),
            'page_items' => $this->items(),
            'page_array' => $this->resource->toArray(),
        ];
    }
}
