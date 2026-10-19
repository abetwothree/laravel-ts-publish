<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Models\Handover;

/**
 * Keyless list literals publish the JSON arrays they encode as: a constant one by its values, any other by its
 * elements' types, a spread of a list adding its elements, and an element no rule types leaving the element `unknown`.
 *
 * @mixin Handover
 */
class HandoverListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lanes = [1, 2];

        return [
            // A constant list, typed by its values.
            'stages' => ['sent', 'received'],
            // A list of reads, typed by the union of its elements.
            'stamps' => [$this->id, $this->created_at],
            // A local holding a list.
            'lanes' => $lanes,
            // A list nested in a keyed literal.
            'route' => ['stops' => ['north', 'south'], 'hops' => 2],
            'empty' => [],
            // A spread of a list renumbers its elements into this one.
            'stops' => [...explode(',', 'north,south'), 'depot'],
            // Enum cases, whose enum the file imports.
            'statuses' => [OrderStatus::Pending, OrderStatus::Shipped],
            // Resources, whose class the file imports.
            'senders' => [new UserResource($this->sender)],
            'legs' => [['from' => 'north', 'to' => 'south'], ['from' => 'south', 'to' => $this->id]],
            'grid' => [[1, 2], [3]],
            // A missing when() value is removed and the list re-indexed, so it stays a list.
            'flags' => [$this->when($request->has('urgent'), 'urgent'), 'standard'],
            // An element no rule types leaves the list a list of unknown.
            'opaque' => [$this->id, json_decode('{}')],
            // The same list through collect(), which publishes the same type.
            'collected' => collect([$this->id, $this->created_at]),
        ];
    }
}
