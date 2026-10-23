<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Wraps each order's required `status` column and its nullable `payment_method` column, each an enum cast, in a
 * relation chain's map and in a map over the whenLoaded() collection, each turned into an array by `->all()`.
 * EnumResource sends null for a null payment method.
 *
 * @mixin User
 */
class MappedOrderEnumColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_statuses' => $this->orders->map(fn ($order) => EnumResource::make($order->status))->all(),
            'order_payment_methods' => $this->orders->map(fn ($order) => EnumResource::make($order->payment_method))->all(),
            'loaded_order_statuses' => $this->whenLoaded('orders', fn ($orders) => $orders->map(fn ($order) => EnumResource::make($order->status))->all()),
            'loaded_order_payment_methods' => $this->whenLoaded('orders', fn ($orders) => $orders->map(fn ($order) => EnumResource::make($order->payment_method))->all()),
        ];
    }
}
