<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\OrderItem;

/**
 * Exercises: whenLoaded with Resource::make, whenLoaded bare (1-arg form),
 * whenNotNull on nullable JSON column.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'order' => $this->whenLoaded('order'),
            'options' => $this->whenNotNull($this->options),
            'order_limited' => $this->order?->only('id', 'total'), // relation with only specific attributes
            'order_extended' => $this->order->except('created_at', 'updated_at'), // relation with except specific attributes
        ];
    }
}
