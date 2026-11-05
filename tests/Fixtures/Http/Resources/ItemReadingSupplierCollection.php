<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\SupplierSummaryCollection;

/** Picks no API resource, so Laravel keeps the raw Supplier models, and reads a member of the one first() returns. */
class ItemReadingSupplierCollection extends SupplierSummaryCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->resource->first()?->name,
            'first_coll_name' => $this->collection->first()?->name,
        ];
    }
}
