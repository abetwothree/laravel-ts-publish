<?php

namespace Workbench\Shipping\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Shipping\Models\Shipment;

/**
 * Exercises: EnumResource::make on two enums (Carrier, Status), when, whenNotNull,
 * whenLoaded bare cross-module (App\Order), Resource::collection,
 * whenCounted, whenAggregated, mergeWhen with complex expression.
 *
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'carrier' => EnumResource::make($this->carrier),
            'status' => EnumResource::make($this->status),
            'weight_grams' => $this->weight_grams,
            'estimated_delivery_at' => $this->whenNotNull($this->estimated_delivery_at),
            'shipped_at' => $this->when($this->shipped_at !== null, $this->shipped_at),
            'delivered_at' => $this->whenNotNull($this->delivered_at),
            'order' => $this->whenLoaded('order'),
            'tracking_events' => TrackingEventResource::collection($this->whenLoaded('trackingEvents')),
            'tracking_events_count' => $this->whenCounted('trackingEvents'),
            'events_total' => $this->whenAggregated('trackingEvents', 'id', 'count'),
            $this->mergeWhen($this->delivered_at !== null, [
                'transit_time' => $this->shipped_at?->diffInDays($this->delivered_at),
            ]),
        ];
    }
}
