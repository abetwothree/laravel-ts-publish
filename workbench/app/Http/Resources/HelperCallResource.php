<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises userland global-helper reflection (route()), Carbon
 * receiver-method inference on a datetime-cast attribute, and the
 * can()/count() known-method rules (Task 11).
 *
 * `diff_result` and `period_result` are Carbon methods that return a Stringable value
 * object json_encode() never writes as its __toString(): a CarbonInterval writes
 * DateInterval's fields, and a CarbonPeriod the list of its dates.
 *
 * `to_mutable`/`to_immutable` are a Task 12 review follow-up: Carbon and
 * CarbonImmutable themselves are `string`, the ISO string their jsonSerialize() writes.
 *
 * `user_key`: `getKey()`'s type depends on which model it's called on, unlike
 * can()/cannot()/canAny() which are bool regardless of receiver. A resource's
 * `$request` is unbound, so `$request->user()` names no model and this stays
 * unknown rather than borrowing the Order this resource wraps.
 *
 * @mixin Order
 */
class HelperCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'route_url' => route('orders.show', $this->resource),
            'ship_date' => $this->created_at->toDateString(),
            'can_edit' => $request->user()->can('update', $this->resource),
            'item_total' => $this->items->count(),
            'diff_result' => $this->created_at->diff($this->paid_at),
            'period_result' => $this->created_at->toPeriod('1 day'),
            'to_mutable' => $this->created_at->toMutable(),
            'to_immutable' => $this->created_at->toImmutable(),
            'user_key' => $request->user()->getKey(),
        ];
    }
}
