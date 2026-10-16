<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\UserResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;

/** Each resource spelling as a page prop, key for key as the two parity middlewares share it. */
class ControllerWithResourceSpellings
{
    public function index(): Response
    {
        return Inertia::render('Parity/Spellings', [
            'built' => new WarehouseResource(Warehouse::query()->firstOrFail()),
            'made' => WarehouseResource::make(Warehouse::query()->firstOrFail()),
            'collected' => WarehouseResource::collection(Warehouse::all()),
            'guessed' => Warehouse::query()->firstOrFail()->toResource(),
            'listed' => Warehouse::all()->toResourceCollection(),
            'role' => EnumResource::make(Role::Admin),
            'statuses' => EnumResource::collection(Status::cases()),
        ]);
    }

    public function jsonApi(): Response
    {
        return Inertia::render('Parity/JsonApi', [
            'post' => new PostResource(Post::query()->firstOrFail()),
            'author' => UserResource::make(User::query()->firstOrFail()),
            'posts' => PostResource::collection(Post::all()),
        ]);
    }
}
