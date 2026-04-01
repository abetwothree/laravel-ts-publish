<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * Exercises: when, whenLoaded + Resource::make, Resource::collection,
 * whenCounted, mergeWhen.
 *
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when($this->is_active, $this->description),
            'is_active' => $this->is_active,
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'members' => TeamMemberResource::collection($this->whenLoaded('members')),
            'members_count' => $this->whenCounted('members'),
            $this->mergeWhen($this->is_active, [
                'settings' => $this->settings,
            ]),
        ];
    }
}
