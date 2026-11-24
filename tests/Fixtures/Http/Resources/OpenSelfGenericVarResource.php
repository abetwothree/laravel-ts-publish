<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\OpenCategoryBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SelfGenericVarResource's `self` keys over a class a subclass may extend, which keep their declared-only reading.
 *
 * @property-read OpenCategoryBranch $resource
 */
class OpenSelfGenericVarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'self_kid_names' => $this->resource->selfKidNames(),
            'self_first_kid_name' => $this->resource->selfFirstKidName(),
            'self_kid_name_list' => $this->resource->selfKidNameList(),
            'self_kid_sort_orders' => $this->resource->selfKidSortOrders(),
            'own_self_kid_name_list' => $this->resource->ownSelfKidNameList(),
            'own_self_first_kid_name' => $this->resource->ownSelfFirstKidName(),
            'own_self_kid_sort_orders' => $this->resource->ownSelfKidSortOrders(),
        ];
    }
}
