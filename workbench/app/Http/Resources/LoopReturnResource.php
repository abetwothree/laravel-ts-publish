<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises collectDirectReturns loop branch in toArray().
 *
 * @mixin Order
 */
class LoopReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        foreach ($this->items as $item) {
            return [
                'id' => $this->id,
                'first_item_name' => $item->name,
            ];
        }

        return [
            'id' => $this->id,
            'total' => $this->total,
        ];
    }
}
