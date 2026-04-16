<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises closure control-flow paths in collectReturnExpressions:
 * elseif, else, switch, try/catch/finally, foreach, and do-while.
 *
 * @mixin Order
 */
class ClosureControlFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Closure with if/elseif/else — exercises lines 234, 238
            'buyer_info' => $this->whenLoaded('user', function () {
                if ($this->user->is_admin) {
                    return ['role' => 'admin', 'name' => $this->user->name];
                } elseif ($this->user->is_manager) {
                    return ['role' => 'manager', 'name' => $this->user->name];
                } else {
                    return ['role' => 'member', 'name' => $this->user->name];
                }
            }),

            // Closure with switch — exercises lines 245-249
            'status_label' => $this->whenLoaded('user', function () {
                switch ($this->status) {
                    case 'active':
                        return ['label' => 'Active'];
                    case 'inactive':
                        return ['label' => 'Inactive'];
                    default:
                        return ['label' => 'Unknown'];
                }
            }),

            // Closure with try/catch/finally — exercises lines 253-263
            'safe_total' => $this->whenLoaded('user', function () {
                try {
                    return ['amount' => $this->total];
                } catch (\Throwable $e) {
                    return ['amount' => 0];
                } finally {
                    return ['amount' => -1];
                }
            }),

            // Closure with foreach — exercises lines 270-272
            'tags' => $this->whenLoaded('user', function () {
                foreach ($this->items as $item) {
                    return ['first_item' => $item->name];
                }

                return ['first_item' => null];
            }),

            // Closure with do-while — exercises line 276
            'retry_result' => $this->whenLoaded('user', function () {
                do {
                    return ['attempted' => true];
                } while (false);
            }),
        ];
    }
}
