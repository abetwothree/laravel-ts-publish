<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises resolveClosureReturnExpression with a Closure passed to merge().
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
                // Closure that returns a non-array expression
                // exercises resolveClosureReturnExpression lines 176-179
                return $this->resource;
            }),
        ];
    }
}
