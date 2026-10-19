<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The collection EnumResource::collection() builds, which filters only its own list of items and never inside an item.
 */
final class EnumResourceCollection extends AnonymousResourceCollection
{
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
