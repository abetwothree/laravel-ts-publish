<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => EnumResource::make($this->status),
            'total' => $this->total,
            'currency' => EnumResource::make($this->currency),
            'items' => $this->whenLoaded('items'),
            'items_count' => $this->whenCounted('items'),
            'total_avg' => $this->whenAggregated('items', 'subtotal', 'avg'),
            'paid_at' => $this->when($this->paid_at !== null, $this->paid_at),
            $this->mergeWhen($this->status?->value === 1, [
                'shipped_at' => $this->shipped_at,
                'delivered_at' => $this->delivered_at,
            ]),
        ];
    }
}
