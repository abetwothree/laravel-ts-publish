<?php

declare(strict_types=1);

namespace Workbench\Shipping\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Shipping\Models\TrackingEvent;

/**
 * Exercises: direct enum property access ($this->status),
 * whenLoaded bare on same-module relation (Shipment).
 *
 * @mixin TrackingEvent
 */
class TrackingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'location' => $this->location,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
            'shipment' => $this->whenLoaded('shipment'),
        ];
    }
}
