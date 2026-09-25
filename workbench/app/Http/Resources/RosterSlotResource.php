<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\RosterSlot;

/**
 * A nullsafe call on a morphTo whose methods the resource's own model also has. Only the relation's generic says
 * what the relation holds, so `MorphTo<Model, $this>` stays unknown and `MorphTo<Crew|Squad, $this>` types.
 *
 * @extends JsonResource<RosterSlot>
 */
final class RosterSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignable_label' => $this->resource->assignable?->singularLabel(),
            'assignable_title' => $this->resource->assignable?->displayTitle(),
            'assignee_label' => $this->resource->assignee?->singularLabel(),
            'assignee_title' => $this->resource->assignee?->displayTitle(),
        ];
    }
}
