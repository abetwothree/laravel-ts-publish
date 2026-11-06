<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects PageItemResource, and reads a member of the item its first() and last() return. */
class ItemReadingPostCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'first_title' => $this->resource->first()?->title,
            'first_coll_title' => $this->collection->first()?->title,
            'last_coll_title' => $this->collection->last()?->title,
            'first_model_title' => $this->resource->first()?->resource->title,
            'first_key' => $this->resource->first()?->getKey(),
        ];
    }
}
