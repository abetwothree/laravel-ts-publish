<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DocblockReads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\DocblockReads\CategoryBranch;

/**
 * `self` inside a generic `@var` in a final class's trait and own body, beside the class named there (GH-134; plan
 * task T3).
 *
 * @property-read CategoryBranch $resource
 */
class SelfGenericVarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'self_kid_names' => $this->resource->selfKidNames(),
            'named_kid_names' => $this->resource->namedKidNames(),
            'self_first_kid_name' => $this->resource->selfFirstKidName(),
            'named_first_kid_name' => $this->resource->namedFirstKidName(),
            'self_kid_name_list' => $this->resource->selfKidNameList(),
            'named_kid_name_list' => $this->resource->namedKidNameList(),
            'self_kid_sort_orders' => $this->resource->selfKidSortOrders(),
            'named_kid_sort_orders' => $this->resource->namedKidSortOrders(),
            'own_self_kid_name_list' => $this->resource->ownSelfKidNameList(),
            'own_named_kid_name_list' => $this->resource->ownNamedKidNameList(),
            'own_self_first_kid_name' => $this->resource->ownSelfFirstKidName(),
            'own_named_first_kid_name' => $this->resource->ownNamedFirstKidName(),
            'own_self_kid_sort_orders' => $this->resource->ownSelfKidSortOrders(),
            'own_named_kid_sort_orders' => $this->resource->ownNamedKidSortOrders(),
        ];
    }
}
