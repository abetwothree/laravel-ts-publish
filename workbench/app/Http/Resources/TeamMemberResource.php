<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Represents a user loaded through a team's belongsToMany pivot.
 *
 * Exercises: whenPivotLoaded, whenPivotLoadedAs, whenHas on enum attributes.
 *
 * @mixin User
 */
#[TsResource(model: User::class)]
class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenHas('role'),
            'membership_level' => $this->whenHas('membership_level'),
            'avatar' => $this->whenNotNull($this->avatar),
            'team_role' => $this->whenPivotLoaded('team_user', function () {
                return $this->pivot->role;
            }),
            'joined_at' => $this->whenPivotLoaded('team_user', function () {
                return $this->pivot->joined_at;
            }),
            'subscription_role' => $this->whenPivotLoadedAs('membership', 'team_user', function () {
                return $this->membership->role;
            }),
        ];
    }
}
