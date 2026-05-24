<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises issue #38: closure parameter passed by the conditional method,
 * where the return expression is a JsonResource make() or collection() call.
 *
 * The bug: the analyzer resolves the return type as `unknown` instead of
 * inferring the resource type (e.g. UserResource or OrderItemResource[]).
 *
 * @mixin Order
 */
class ConditionalParamJsonResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Arrow fn param → Resource::make($param)
            'user' => $this->whenLoaded('user', fn ($user) => UserResource::make($user)),

            // Arrow fn param → Resource::collection($param)
            'items' => $this->whenLoaded('items', fn ($items) => OrderItemResource::collection($items)),

            // when() with arrow fn param → Resource::make($param)
            'user_when' => $this->when(
                $this->user !== null,
                fn ($user) => UserResource::make($user)
            ),
        ];
    }
}
