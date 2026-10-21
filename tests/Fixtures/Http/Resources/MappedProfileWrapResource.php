<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * A resource wrapped around a member's nullable profile inside a map(). JsonResource::filter() turns it into null only
 * where it reaches the mapped items as an array; inside a Collection the wrap serializes itself, and ProfileResource
 * throws on its null payload.
 *
 * @mixin Team
 */
class MappedProfileWrapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'profiles' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => $m->profile)),
            'wrapped' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])),
            'wrapped_values' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->values()),
            'wrapped_all' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->all()),
            'chain_wrapped' => $this->members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)]),
            'chain_wrapped_all' => $this->members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->all(),
            'owner_wrapped' => $this->whenLoaded('owner', fn ($owner) => ['profile' => new ProfileResource($owner->profile)]),
        ];
    }
}
