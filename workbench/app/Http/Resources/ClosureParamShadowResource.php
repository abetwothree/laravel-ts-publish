<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A closure parameter that shadows a top-level local resolves through its own scoped binding
 * (whenLoaded relation / relation-chain element model) for the closure's body only, and must not
 * leak the outer local's value. Outside the closure, `outer_member` proves the shadowing param no
 * longer suppresses the top-level `$member` local's own binding.
 *
 * @mixin Team
 */
class ClosureParamShadowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->slug;
        $owner = $this->description;
        $shadowed = $this->name;

        // The closure writes only its own `$shadowed` parameter, so the outer local keeps its binding.
        $this->members->each(function ($shadowed) {
            $shadowed = $shadowed->name;
        });

        return [
            'outer_member' => $member,
            'mapped_members' => $this->members->take(5)->map(fn ($member) => $member)->values(),
            'loaded_owner' => $this->whenLoaded('owner', function ($owner) {
                return $owner;
            }),
            // 'members' is a to-many relation: the closure param holds the whole collection, not one
            // element, so it resolves to the collection type `User[]` — never the bare element `User`.
            'loaded_members_bare' => $this->whenLoaded('members', fn ($members) => $members),
            'shadowed' => $shadowed,
        ];
    }
}
