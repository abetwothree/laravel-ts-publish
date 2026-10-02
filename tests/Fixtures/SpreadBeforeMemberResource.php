<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;

/**
 * A test-only resource whose member spreads one resource, then names another that shares its basename.
 *
 * @mixin Team
 */
class SpreadBeforeMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'members' => $this->whenLoaded('members', fn ($members) => $members->map(
                fn (User $member) => [
                    ...CrmUserResource::make($member)->resolve($request),
                    'peer' => new UserResource($member),
                ]
            )),
        ];
    }
}
