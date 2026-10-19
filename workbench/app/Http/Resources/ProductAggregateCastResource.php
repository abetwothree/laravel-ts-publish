<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Product;

/**
 * Aggregates whose alias the parent model casts: `decimal:2` writes a string, and `timestamp` the Unix integer.
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
            'unit_price_total' => $this->whenAggregated('orderItems', 'unit_price', 'sum'),
        ];
    }
}
