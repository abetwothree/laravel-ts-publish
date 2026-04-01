<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises advanced merge patterns: mergeWhen with EnumResource::make,
 * mergeWhen with Resource::make, whenLoaded with value arg.
 *
 * @mixin Order
 */
class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => EnumResource::make($this->status),
            'user' => $this->whenLoaded('user', UserResource::make($this->whenLoaded('user'))),
            $this->mergeWhen($this->paid_at !== null, [
                'payment_status' => EnumResource::make($this->status),
                'payment_currency' => $this->currency,
                'shipping_user' => UserResource::make($this->whenLoaded('user')),
                'order_items' => $this->whenLoaded('items'),
            ]),
        ];
    }
}
