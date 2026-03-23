<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises closure / arrow function patterns in value expressions and merge methods.
 *
 * @mixin Order
 */
class OrderClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Arrow function returning $this->property
            'status_arrow' => $this->when(true, fn () => $this->status),
            // Arrow function returning Resource::make()
            'user_arrow' => $this->when(true, fn () => UserResource::make($this->user)),
            // Arrow function returning Resource::collection()
            'items_arrow' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            // Full closure with return statement
            'notes_closure' => $this->when(true, function () {
                return $this->notes;
            }),
            // mergeWhen with closure returning array
            $this->mergeWhen($this->paid_at !== null, fn () => [
                'shipped_at' => $this->shipped_at,
                'tracking' => $this->notes,
            ]),
            // merge with closure returning array
            $this->merge(fn () => [
                'currency_label' => $this->currency,
            ]),
            // merge with array literal (unconditional)
            $this->merge([
                'total_display' => $this->total,
            ]),
        ];
    }
}
