<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Wraps each user's nullable `role` in maps over a query and a local (each also kept as a Collection, the local also by
 * `new`), a hinted local, a `collect()` of a relation and the relation; the first two and the `collect()` again in
 * whenLoaded(); and under `instanceof`. A null role sends null where `->all()` hands the map to filter(), else `[]`.
 *
 * @mixin Team
 */
class MappedEnumColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $users = User::query()->get();

        return [
            'query_roles' => User::query()->get()->map(fn ($user) => EnumResource::make($user->role))->all(),
            'local_roles' => $users->map(fn ($user) => EnumResource::make($user->role))->all(),
            'kept_query_roles' => User::query()->get()->map(fn ($user) => EnumResource::make($user->role)),
            'kept_local_roles' => $users->map(fn ($user) => EnumResource::make($user->role)),
            'new_local_roles' => $users->map(fn ($user) => new EnumResource($user->role))->all(),
            'kept_new_local_roles' => $users->map(fn ($user) => new EnumResource($user->role)),
            'hinted_local_roles' => $users->map(fn (User $user) => EnumResource::make($user->role))->all(),
            'collected_roles' => collect($this->members)->map(fn ($member) => EnumResource::make($member->role))->all(),
            'chain_roles' => $this->members->map(fn ($member) => EnumResource::make($member->role))->all(),
            'nested_query_roles' => $this->whenLoaded('owner', function ($owner) {
                return User::query()->get()->map(fn ($user) => EnumResource::make($user->role))->all();
            }),
            'nested_local_roles' => $this->whenLoaded('owner', function ($owner) {
                $users = User::query()->get();

                return $users->map(fn ($user) => EnumResource::make($user->role))->all();
            }),
            'nested_collected_roles' => $this->whenLoaded('members', fn ($members) => collect($members)->map(fn ($member) => EnumResource::make($member->role))->all()),
            'narrowed_member_roles' => $this->whenLoaded('members', fn ($members) => $members instanceof EloquentCollection
                ? $members->map(fn ($member) => EnumResource::make($member->role))->all()
                : null),
        ];
    }
}
