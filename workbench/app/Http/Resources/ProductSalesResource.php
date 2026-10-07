<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Product;

/**
 * Exercises: whenAggregated() typed from the aggregated column and the connection's driver (SQLite here), its null arm
 * over no rows, a value closure passed the aggregate, and whenCounted(), whose count is never null.
 *
 * @mixin Product
 */
class ProductSalesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'last_sold_at' => $this->whenAggregated('orderItems', 'created_at', 'max'),
            'first_item_name' => $this->whenAggregated('orderItems', 'name', 'min'),
            'average_quantity' => $this->whenAggregated('orderItems', 'quantity', 'avg'),
            'top_quantity' => $this->whenAggregated('orderItems', 'quantity', 'max', fn ($max) => ['max' => $max]),
            'has_bulk_line' => $this->whenAggregated('orderItems', 'quantity', 'max', fn ($max) => $max >= 10),
            'revenue' => $this->whenAggregated('orderItems', 'total_price', 'sum', null, 0),
            'has_items' => $this->whenCounted('orderItems', fn ($count) => $count > 0),
        ];
    }
}
