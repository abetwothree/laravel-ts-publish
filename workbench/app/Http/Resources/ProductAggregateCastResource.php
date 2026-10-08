<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Product;

/**
 * An aggregate whose alias the parent model casts: a `timestamp` cast writes the Unix integer.
 *
 * @mixin Product
 */
class ProductAggregateCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'first_sold_ts' => $this->whenAggregated('orderItems', 'created_at', 'min'),
        ];
    }
}
