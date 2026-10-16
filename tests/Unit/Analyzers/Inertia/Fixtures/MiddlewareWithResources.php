<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

/** Shared resources, which Inertia sends as page props, beside an EnumResource whose empty wrap sends it bare. */
final class MiddlewareWithResources extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'firstShelf' => new WarehouseResource(Warehouse::query()->first()),
            'shelfList' => WarehouseResource::collection(Warehouse::all()),
            'named' => new PostCollection(Post::all()),
            'statuses' => EnumResource::collection(Status::cases()),
            'role' => EnumResource::make(Role::Admin),
            'user' => $request->user()?->toResource(),
        ];
    }
}
