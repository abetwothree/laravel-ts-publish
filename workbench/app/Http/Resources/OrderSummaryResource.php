<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises direct property access for accessors, mutators, and relations
 * without using whenLoaded or other conditional wrappers.
 *
 * @mixin Order
 */
class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // DB column with accessor (cast='attribute') — should resolve accessor return type
            'is_paid' => $this->is_paid,
            // Pure mutator (non-DB attribute) — should resolve accessor return type
            'item_count' => $this->item_count,
            // Another pure mutator — should resolve string return type
            'formatted_total' => $this->formatted_total,
            // Direct relation access without whenLoaded — should resolve relation type
            'user' => $this->user,
            // Regular DB column with enum cast — should resolve enum type
            'status' => $this->status,
            // Regular DB column — should resolve from DB type
            'total' => $this->total,
            // Nullable DB column with accessor — should resolve accessor type + null
            'notes' => $this->notes,
            // Write-only mutator (no getter) on non-DB column — should resolve to unknown
            'search_index' => $this->search_index,
        ];
    }
}
