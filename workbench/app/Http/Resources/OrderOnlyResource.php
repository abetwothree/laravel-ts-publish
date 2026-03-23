<?php

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises ...$this->only([...]) spread with additional manual keys.
 *
 * @mixin Order
 */
class OrderOnlyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only([
                'id',
                'total',
                'status',
            ]),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
