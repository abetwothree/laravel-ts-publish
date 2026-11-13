<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects PageItemResource, and picks at random off collections derived from `$this->collection`. */
class DerivedItemsPostCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'values' => $this->collection->values()->random(),
            'title' => $this->collection->pluck('title')->random(),
            'title_pair' => $this->collection->pluck('title')->random(2),
            'base' => $this->collection->toBase()->random(),
            'filtered' => $this->collection->filter()->random(),
            'mapped' => $this->collection->map(fn ($item) => $item->id)->random(),
            'mapped_pair' => $this->collection->map(fn ($item) => $item->id)->random(2),
        ];
    }
}
