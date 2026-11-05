<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Maps a team's members, or its owner's posts, to `EnumResource` wraps of a nullable enum: kept as a Collection, handed
 * to filter() as an array by `->all()` or `->toArray()`, or each resolved at once by `->jsonSerialize()`; and maps each
 * member to such a map of its posts, which `->toArray()` turns into an array too.
 *
 * @mixin Team
 */
class KeptEnumMapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'values_kept' => $this->members->map(fn (User $u) => EnumResource::make($u->role))->values(),
            'filter_map_kept' => $this->members->filter()->map(fn (User $u) => EnumResource::make($u->role)),
            'filter_map_all' => $this->members->filter()->map(fn (User $u) => EnumResource::make($u->role))->all(),
            'untyped_kept' => $this->members->map(fn ($u) => EnumResource::make($u->role)),
            'whenloaded_kept' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $u) => EnumResource::make($u->role))),
            'collect_kept' => collect($this->members)->map(fn ($m) => EnumResource::make($m->role)),
            'backed_kept' => $this->owner->posts->map(fn (Post $p) => EnumResource::make($p->priority)),
            'backed_all' => $this->owner->posts->map(fn (Post $p) => EnumResource::make($p->priority))->all(),
            'to_array' => $this->members->map(fn (User $u) => EnumResource::make($u->role))->toArray(),
            'json_serialized' => $this->members->map(fn (User $u) => EnumResource::make($u->role))->jsonSerialize(),
            'nested_all' => $this->members->map(fn (User $u) => $u->posts->map(fn (Post $p) => EnumResource::make($p->priority)))->all(),
            'nested_to_array' => $this->members->map(fn (User $u) => $u->posts->map(fn (Post $p) => EnumResource::make($p->priority)))->toArray(),
            'nested_collect_to_array' => collect($this->members)->map(fn (User $u) => $u->posts->map(fn (Post $p) => EnumResource::make($p->priority)))->toArray(),
            'nested_loaded_to_array' => $this->whenLoaded('members', fn ($members) => $members->map(fn (User $u) => $u->posts->map(fn (Post $p) => EnumResource::make($p->priority)))->toArray()),
            'tried_kept' => $this->members->map(fn (User $u) => EnumResource::make(Status::tryFrom($u->id)))->values(),
            'tried_all' => $this->members->map(fn (User $u) => EnumResource::make(Status::tryFrom($u->id)))->all(),
        ];
    }
}
