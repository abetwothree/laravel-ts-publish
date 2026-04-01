<?php

declare(strict_types=1);

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
                // Use "only" with keys that aren't directly DB attributes
                'notes', // accessor publish with accessor return type
                'item_count', // mutator publish with mutator return type
                'items', // relation publish with relation return type & import
            ]),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
