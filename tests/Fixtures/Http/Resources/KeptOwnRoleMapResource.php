<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\NullableWrapBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;

/**
 * Wraps the user's own nullable role, or each queried user's inside an array, in maps kept as a Collection or ended by
 * `->all()`; and wraps the status `tryFrom()` finds for the request's `status`, which may be none, here or in a helper.
 *
 * @mixin User
 */
class KeptOwnRoleMapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $users = User::query()->get();

        return [
            'own_role_kept' => $users->map(fn ($u) => EnumResource::make($this->role)),
            'own_role_all' => $users->map(fn ($u) => EnumResource::make($this->role))->all(),
            'element_kept' => $users->map(fn ($u) => ['role' => EnumResource::make($u->role)]),
            'element_all' => $users->map(fn ($u) => ['role' => EnumResource::make($u->role)])->all(),
            'tried' => EnumResource::make(Status::tryFrom($request->integer('status'))),
            'from' => EnumResource::make(Status::from($request->integer('status') % 2)),
            'helper_tried' => NullableWrapBuilder::status($request->integer('status')),
        ];
    }
}
