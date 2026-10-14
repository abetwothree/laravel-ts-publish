<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A collection that names the model it collects, whose methods share names with Support\Collection's.
 *
 * @mixin ProxyCollectedPost
 */
final class ProxyCollectedPostCollection extends ResourceCollection
{
    public $collects = ProxyCollectedPostResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'first_bare' => $this->first(),
            'first_proxy' => $this->resource->first(),
            'page' => $this->resource,
            'page_array' => $this->resource->toArray(),
            'count_proxy' => $this->resource->count(),
            'values_bare' => $this->values(),
            'sum_bare' => $this->sum('id'),
            'title_bare' => $this->title,
            'title_proxy' => $this->resource->title,
            'only_bare' => $this->only(['id']),
            'key_bare' => $this->getKey(),
            'pluck_proxy' => $this->resource->pluck('title'),
            'collection' => $this->collection,
        ];
    }
}
