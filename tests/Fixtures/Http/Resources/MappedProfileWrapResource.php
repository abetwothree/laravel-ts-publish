<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\NullableWrapBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * A resource wrapped around a nullable profile inside a map(): a member's, or in a collect() pipeline the owner's too.
 * JsonResource::filter() turns it into null only where it reaches the wrap: a mapped item handed over as an array by
 * `->all()` or `->toArray()`, or a helper's; inside a Collection the wrap serializes itself, so ProfileResource throws.
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
            'pipe_owner_wrapped' => collect($this->members)->map(fn (User $m) => ['profile' => new ProfileResource($this->owner?->profile)]),
            'pipe_owner_wrapped_all' => collect($this->members)->map(fn (User $m) => ['profile' => new ProfileResource($this->owner?->profile)])->all(),
            'pipe_member_wrapped' => collect($this->members)->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)]),
            'pipe_member_wrapped_all' => collect($this->members)->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->all(),
            'owner_wrapped' => $this->whenLoaded('owner', fn ($owner) => ['profile' => new ProfileResource($owner->profile)]),
            'wrapped_to_array' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->toArray()),
            'wrapped_serialized' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => ['profile' => new ProfileResource($m->profile)])->jsonSerialize()),
            'direct' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => new ProfileResource($m->profile))),
            'direct_all' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => new ProfileResource($m->profile))->all()),
            'direct_to_array' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $m) => new ProfileResource($m->profile))->toArray()),
            'chain_direct_to_array' => $this->members->map(fn (User $m) => new ProfileResource($m->profile))->toArray(),
            'pipe_direct_to_array' => collect($this->members)->map(fn (User $m) => new ProfileResource($m->profile))->toArray(),
            'helper_owner_profile' => NullableWrapBuilder::profile($this->owner),
        ];
    }
}
