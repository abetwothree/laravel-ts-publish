<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Wraps a user's nullable `role` column read through a model bound outside any relation closure: a helper's declared
 * parameter, and a method-level `foreach` over a relation. EnumResource sends null for a null role.
 *
 * @mixin Team
 */
class BoundEnumColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        foreach ($this->members as $member) {
            return [
                ...$this->ownerRole($this->resource->owner),
                'member_role' => EnumResource::make($member->role),
            ];
        }

        return [];
    }

    /**
     * The role of the user passed in.
     *
     * @return array<string, mixed>
     */
    private function ownerRole(User $user): array
    {
        return ['owner_role' => EnumResource::make($user->role)];
    }
}
