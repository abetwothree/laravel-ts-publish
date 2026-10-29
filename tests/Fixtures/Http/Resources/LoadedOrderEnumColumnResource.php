<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\OrderItem;

/**
 * Wraps the loaded order's required `status` column and its nullable `payment_method` column, each an enum cast, inside
 * a whenLoaded() closure. EnumResource sends null for a null payment method.
 *
 * @mixin OrderItem
 */
class LoadedOrderEnumColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_status' => $this->whenLoaded('order', fn ($order) => EnumResource::make($order->status)),
            'order_payment_method' => $this->whenLoaded('order', fn ($order) => EnumResource::make($order->payment_method)),
        ];
    }
}
