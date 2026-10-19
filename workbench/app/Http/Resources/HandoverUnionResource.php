<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;

/**
 * `+` on arrays publishes PHP's array union, where the left operand's keys win and the right one adds the rest, and
 * `+` on numbers stays a number.
 *
 * @mixin Handover
 */
class HandoverUnionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $defaults = ['note' => 'none', 'id' => 'unset'];

        return [
            // An attribute filter plus defaults: the filter's id wins over the default's.
            'with_defaults' => $this->only(['id', 'sender_id']) + $defaults,
            // Two literals: the left code wins, the right one adds lane.
            'override' => ['code' => 'in'] + ['code' => 0, 'lane' => 2],
            // Two lists: [1, 2] + [3, 4, 5] is [1, 2, 5].
            'lanes' => [1, 2] + [3, 4, 5],
            'chained' => ['a' => 1] + ['b' => 'x'] + ['c' => true],
            'total' => $this->id + $this->sender_id,
        ];
    }
}
