<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DataKeyPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ItemsKeyPostCollection;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

class InertiaResourcePropsController
{
    /**
     * Single resources, an enum collection and a collection body without its wrap key, at the top level and nested.
     */
    public function show(Warehouse $warehouse): Response
    {
        return Inertia::render('Resources/Props', [
            'built' => new WarehouseResource($warehouse),
            'made' => WarehouseResource::make($warehouse),
            'guessed' => $warehouse->toResource(),
            'named' => $warehouse->toResource(WarehouseResource::class),
            'nested' => ['inner' => new WarehouseResource($warehouse)],
            'statuses' => EnumResource::collection(Status::cases()),
            'custom' => new ItemsKeyPostCollection(Post::all()),
            'custom_page' => new ItemsKeyPostCollection(Post::query()->paginate()),
            'held' => new DataKeyPostResource($warehouse),
        ]);
    }

    /**
     * The body resolve() returns for a resource and for a collection, and a fluent call on a single resource.
     */
    public function chained(Warehouse $warehouse): Response
    {
        return Inertia::render('Resources/Chained', [
            'resolved' => (new WarehouseResource($warehouse))->resolve(),
            'made_resolved' => WarehouseResource::make($warehouse)->resolve(),
            'additional' => WarehouseResource::make($warehouse)->additional(['meta' => 1]),
            'list' => (new PostCollection(Post::all()))->resolve(),
            'body' => (new ItemsKeyPostCollection(Post::all()))->resolve(),
        ]);
    }
}
