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
 * Wraps each user's nullable `role` column in maps over a query, a local holding one, with and without a model hint, a
 * `collect()` of a relation and the relation itself, in the first two and a `collect()` nested in a whenLoaded()
 * closure, and under an `instanceof` test on a loaded relation. EnumResource sends null for a null role.
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
