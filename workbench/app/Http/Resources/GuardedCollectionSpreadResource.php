<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * A guard-clause branch alongside a top-level `[key: number]` collection spread — regression
 * fixture for mergeReturnBranches() over-marking an index signature optional.
 *
 * @mixin Order
 */
class GuardedCollectionSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->status === 'archived') {
            return [
                'id' => $this->id,
                'archived' => true,
            ];
        }

        return [
            'id' => $this->id,
            ...$this->items->toArray(),
        ];
    }
}
