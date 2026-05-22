<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;

/**
 * Exercises issue #38: the exact bug pattern from the issue report.
 * A closure receives the loaded relation as a parameter and calls ->map()
 * with a nested inner closure that returns an array shape.
 *
 * The bug: the outer closure param return type resolves to `unknown` instead of
 * inferring the mapped array shape `{ id: number; name: string; quantity: number }[]`.
 *
 * @mixin Order
 */
class ConditionalParamMappedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Exact pattern from issue #38: outer param + inner typed param
            'items_mapped' => $this->whenLoaded('items', fn ($items) => $items->map(fn (OrderItem $item) => [
                'id'       => $item->id,
                'name'     => $item->name,
                'quantity' => $item->quantity,
            ])),

            // Variant: map with unit_price included
            'items_priced' => $this->whenLoaded('items', fn ($items) => $items->map(fn (OrderItem $item) => [
                'id'         => $item->id,
                'sku'        => $item->sku,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])),

            // Variant: pluck a single value from each item
            'item_names' => $this->whenLoaded('items', fn ($items) => $items->pluck('name')),
        ];
    }
}
