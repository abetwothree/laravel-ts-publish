<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Models\Post;

/**
 * A collection of PageItemResource that keeps its source keys.
 *
 * @mixin Post
 */
class PageKeyedCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    public $preserveKeys = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'page' => $this->resource,
            'items' => $this->resource->items(),
        ];
    }
}
