<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises mergeWhen() and mergeUnless() with a default, which Laravel merges when the condition fails. A key only
 * one side sets is optional, and a key both sides set is required, typed with both sides' types.
 *
 * @mixin Order
 */
class MergeDefaultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // An array default: `state` is on both sides, so it is always present.
            $this->mergeWhen($this->paid_at !== null, [
                'state' => 'paid',
                'paid_by' => $this->user_id,
            ], [
                'state' => 0,
                'awaiting_payment' => true,
            ]),

            // A closure default, which Laravel calls with no arguments, as it calls the value closure.
            $this->mergeUnless($this->cancelled_at === null, fn () => [
                'cancelled' => true,
            ], fn () => [
                'cancelled' => false,
                'open_since' => $this->placed_at,
            ]),

            // A named default is read by name.
            $this->mergeWhen(default: ['note_text' => null], condition: $this->notes !== '', value: [
                'note_text' => $this->notes,
            ]),

            // A default closure's `return []` merges nothing, so the key it guards is optional.
            $this->mergeWhen($this->user_id > 0, ['owner_id' => $this->user_id], function () {
                if ($this->ip_address === null) {
                    return [];
                }

                return ['owner_id' => 0];
            }),

            // Without a default, a failed condition merges nothing, so every key stays optional.
            $this->mergeWhen($this->subtotal > 0, ['subtotal_label' => 'set']),

            // A default the engine cannot type drops out of the union, as a spread helper's untypable branch does.
            $this->mergeWhen($this->id > 0, ['k' => $this->total], ['k' => $this->opaqueTotal()]),

            // A `null` value beside an untypable default keeps its `null`, as a ternary keeps its one typed arm.
            $this->mergeWhen($this->total === null, ['null_total' => null], ['null_total' => $this->opaqueTotal()]),
        ];
    }

    /**
     * Deliberately untyped, so the default that reads it is a side the engine cannot type.
     */
    public function opaqueTotal()
    {
        return $this->resource->getAttribute('total');
    }
}
