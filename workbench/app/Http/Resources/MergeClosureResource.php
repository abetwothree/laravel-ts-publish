<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises closureReturnBranches() with a Closure passed to merge().
 * The closure has a guard clause followed by the real array return.
 *
 * @mixin Order
 */
class MergeClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(function () {
                if (! $this->user) {
                    return [];
                }

                return [
                    'user_name' => $this->user->name,
                    'user_email' => $this->user->email,
                ];
            }),
            $this->mergeWhen(true, function () {
                // A closure that returns a non-array expression: closureReturnBranches() reads no branch from it
                return $this->resource;
            }),
        ];
    }
}
