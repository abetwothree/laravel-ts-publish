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

/**
 * Each resource spelling as a page prop, key for key as the two parity middlewares share it, and inside Inertia's lazy
 * prop wrappers.
 */
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

    public function wrapped(): Response
    {
        return Inertia::render('Parity/Wrapped', [
            'deferred' => Inertia::defer(fn () => WarehouseResource::collection(Warehouse::all())),
            'optional' => Inertia::optional(fn () => WarehouseResource::make(Warehouse::query()->firstOrFail())),
            'lazy' => Inertia::lazy(fn () => Warehouse::all()->toResourceCollection()),
            'statuses' => Inertia::defer(fn () => EnumResource::collection(Status::cases())),
            'role' => Inertia::optional(fn () => EnumResource::make(Role::Admin)),
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
