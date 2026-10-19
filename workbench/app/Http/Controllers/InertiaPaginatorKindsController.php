<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PostStubCollection;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\Warehouse;

/**
 * A resource collection over each paginator kind: Laravel's `meta` block differs for `paginate()`, `simplePaginate()`
 * and `cursorPaginate()`, so each kind publishes its own pagination type.
 */
class InertiaPaginatorKindsController
{
    /**
     * A wrapping collection keeps its own type beside the pagination members of each paginator it may wrap.
     *
     * Result should be { simple: PostCollection & ResourceSimplePagination,
     * cursor: PostCollection & ResourceCursorPagination, either: PostCollection & (ResourceSimplePagination |
     * ResourcePagination), sometimes: PostCollection | (PostCollection & ResourcePagination) }
     */
    public function named(Request $request): Response
    {
        return Inertia::render('PaginatorKinds/Named', [
            'simple' => new PostCollection(Post::query()->simplePaginate(10)),
            'cursor' => new PostCollection(Post::query()->cursorPaginate(10)),
            'either' => new PostCollection($request->boolean('simple') ? Post::query()->simplePaginate(10) : Post::query()->paginate(10)),
            'sometimes' => new PostCollection($request->boolean('all') ? Post::all() : Post::query()->paginate(10)),
        ]);
    }

    /**
     * An anonymous collection over a paginator is that paginator's resource response.
     *
     * Result should be { simple: JsonResourceSimplePaginator<PostResource>,
     * cursor: JsonResourceCursorPaginator<PostResource>,
     * sometimes: AnonymousResourceCollection<PostResource> | JsonResourceSimplePaginator<PostResource> }
     */
    public function anonymous(Request $request): Response
    {
        return Inertia::render('PaginatorKinds/Anonymous', [
            'simple' => PostResource::collection(Post::query()->simplePaginate(10)),
            'cursor' => PostResource::collection(Post::query()->cursorPaginate(10)),
            'sometimes' => PostResource::collection($request->boolean('all') ? Post::all() : Post::query()->simplePaginate(10)),
        ]);
    }

    /**
     * A flat collection ($wrap = null) over a paginator is sent under `data`, as the paginator's resource response.
     *
     * Result should be { simple: JsonResourceSimplePaginator<PostResource>,
     * cursor: JsonResourceCursorPaginator<PostResource>,
     * keyed: Omit<JsonResourceCursorPaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> } }
     */
    public function flat(): Response
    {
        return Inertia::render('PaginatorKinds/Flat', [
            'simple' => new PostFlatCollection(Post::query()->simplePaginate(10)),
            'cursor' => new PostFlatCollection(Post::query()->cursorPaginate(10)),
            'keyed' => new PreserveKeysFlatCollection(Team::query()->cursorPaginate(10)),
        ]);
    }

    /**
     * toResourceCollection() builds the collection Laravel guesses, PostCollection for a post and WarehouseResource's
     * anonymous one for a warehouse, or the given resource's anonymous one, and is sent as that collection's response.
     *
     * Result should be { named: PostCollection & ResourceSimplePagination,
     * anonymous: JsonResourceCursorPaginator<WarehouseResource>, explicit: JsonResourcePaginator<WarehouseResource>,
     * unpaged: PostCollection }
     */
    public function guessed(): Response
    {
        return Inertia::render('PaginatorKinds/Guessed', [
            'named' => Post::query()->simplePaginate(10)->toResourceCollection(),
            'anonymous' => Warehouse::query()->cursorPaginate(10)->toResourceCollection(),
            'explicit' => Warehouse::query()->paginate(10)->toResourceCollection(WarehouseResource::class),
            'unpaged' => Post::all()->toResourceCollection(),
        ]);
    }

    /**
     * The `make:resource --collection` stub's body returns parent::toArray(), the collected list, so Laravel wraps it
     * under `data` beside the simple paginator's members, and PostStubCollection publishes that `data` key.
     *
     * Result should be { posts: PostStubCollection & ResourceSimplePagination }
     */
    public function stubPage(): Response
    {
        return Inertia::render('PaginatorKinds/StubPage', [
            'posts' => new PostStubCollection(Post::query()->simplePaginate(10)),
        ]);
    }
}
