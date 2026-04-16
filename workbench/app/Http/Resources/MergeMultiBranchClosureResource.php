<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises resolveArrayOrClosureToProperties with a multi-return closure
 * passed to merge(). The closure has multiple branches returning different
 * array shapes, which should be merged with union semantics.
 *
 * @mixin Order
 */
class MergeMultiBranchClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(function () {
                if ($this->status === 'archived') {
                    return [
                        'archived_at' => $this->cancelled_at,
                    ];
                }

                return [
                    'total' => $this->total,
                    'currency' => $this->currency,
                ];
            }),
        ];
    }
}
