<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Enumerable;

/** Collects PageItemResource, hands `$this->collection` to a helper declaring an interface, and steps its paginator. */
class PagedHelpersPostCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'helper_ids' => $this->pickedId($this->collection),
            'side' => $this->resource->onEachSide(2),
        ];
    }

    /**
     * One id picked off any collection of items.
     */
    private function pickedId(Enumerable $items)
    {
        return $items->pluck('id')->random();
    }
}
