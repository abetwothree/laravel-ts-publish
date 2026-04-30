<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

class InertiaCollectionsController
{
    public function lengthAware(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/LengthAware', [
            'posts' => $posts,
        ]);
    }

    public function simple(): Response
    {
        $posts = Post::latest()->simplePaginate(25);

        return Inertia::render('Collections/Simple', [
            'posts' => $posts,
        ]);
    }

    public function cursor(): Response
    {
        $posts = Post::latest()->cursorPaginate(25);

        return Inertia::render('Collections/Cursor', [
            'posts' => $posts,
        ]);
    }

    public function resource(): Response
    {
        $posts = Warehouse::latest()->paginate(25);

        return Inertia::render('Collections/Resource', [
            'posts' => new WarehouseResource($posts),
        ]);
    }

    public function resourceGet(): Response
    {
        $posts = Warehouse::latest()->limit(25)->get();

        return Inertia::render('Collections/ResourceGet', [
            'posts' => new WarehouseResource($posts),
        ]);
    }

    public function resourceAnonymous(): Response
    {
        $posts = Post::latest()->limit(25)->get();

        return Inertia::render('Collections/ResourceAnonymous', [
            'posts' => PostResource::collection($posts),
        ]);
    }

    public function namedCollection(): Response
    {
        $posts = Post::all();

        return Inertia::render('Collections/Named', [
            'posts' => new PostCollection($posts),
        ]);
    }

    public function namedCollectionPaginated(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/NamedPaginated', [
            'posts' => new PostCollection($posts),
        ]);
    }

    public function flatCollectionPaginated(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/FlatPaginated', [
            'posts' => new PostFlatCollection($posts),
        ]);
    }
}
