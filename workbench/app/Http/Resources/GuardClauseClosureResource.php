<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises the bug where resolveClosureReturnExpression() picks the first
 * Return_ statement in a closure — which is the guard-clause `return null`
 * instead of the actual data array.
 *
 * The closure has:
 *   if (! $this->user) { return null; }  ← guard clause (first return)
 *   return [ 'name' => ..., 'email' => ... ];  ← actual data (should be picked)
 *
 * @mixin Order
 */
class GuardClauseClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'buyer' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
