<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The collection EnumResource::collection() builds, which filters only its own list of items and never inside an item.
 */
final class EnumResourceCollection extends AnonymousResourceCollection
{
    /**
     * Resolve each item through its own resource, as Laravel 13 does, so every version filters an item the same way.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection?->map(fn (mixed $item): mixed => $item instanceof JsonResource ? $item->resolve($request) : $item)->all() ?? [];
    }

    /**
     * Drop missing items and list the rest, leaving each item's arrays with the keys the published enum keeps.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function filter($data): array
    {
        return $this->removeMissingValues($data);
    }
}
