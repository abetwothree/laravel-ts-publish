<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises issue #38: closure parameter passed by the conditional method,
 * where the return expression wraps an enum in EnumResource::make() or returns it bare.
 *
 * The bug: the analyzer resolves the return type as `unknown` instead of
 * recognising the enum type from the param or the EnumResource wrapper.
 *
 * @mixin Order
 */
class ConditionalParamEnumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // when() with arrow fn param → EnumResource::make($param)
            'status_resource' => $this->when(
                $this->status !== null,
                fn ($status) => EnumResource::make($status)
            ),

            // when() with arrow fn param → bare enum (no wrapper)
            'status_bare' => $this->when(
                $this->status !== null,
                fn ($status) => $status
            ),

            // when() with arrow fn param → EnumResource::make($param) on currency
            'currency_resource' => $this->when(
                $this->currency !== null,
                fn ($currency) => EnumResource::make($currency)
            ),

            // whenLoaded with arrow fn param → EnumResource::make on relation property
            'user_role' => $this->whenLoaded('user', fn ($user) => EnumResource::make($user->role)),
        ];
    }
}
