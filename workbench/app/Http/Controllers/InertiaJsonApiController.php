<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\JsonApi\CategoryResource;
use Workbench\App\Http\Resources\JsonApi\CommentResource;
use Workbench\App\Http\Resources\JsonApi\KpiResource;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\UserResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Post;

/**
 * JSON:API resources as Inertia props, which Inertia sends as each one's whole document. Its routes are registered
 * only on a Laravel that ships JsonApiResource, 12.45 and later.
 */
class InertiaJsonApiController
{
    /**
     * A single resource object's document, whose `included` holds every resource an include can reach.
     *
     * Result should be { post: JsonApiDocument<PostResource, UserResource | CommentResource | CategoryResource>,
     * author: JsonApiDocument<UserResource>, category: JsonApiDocument<CategoryResource> | null,
     * kpi: JsonApiDocument<KpiResource, JsonApiResourceObject<'reports'>> }
     */
    public function show(Post $post): Response
    {
        return Inertia::render('JsonApi/Show', [
            'post' => new PostResource($post),
            'author' => UserResource::make($post->author()->firstOrFail()),
            'category' => $post->categoryRel?->toResource(CategoryResource::class),
            'kpi' => new KpiResource(Kpi::query()->firstOrFail()),
        ]);
    }

    /**
     * A collection's document over a list and over each paginator kind.
     *
     * Result should be { posts: JsonApiCollection<PostResource, UserResource | CommentResource | CategoryResource>,
     * paged: JsonApiPaginator<…>, simple: JsonApiSimplePaginator<…>, cursor: JsonApiCursorPaginator<…>,
     * comments: JsonApiCollection<CommentResource, UserResource> }
     */
    public function index(): Response
    {
        return Inertia::render('JsonApi/Index', [
            'posts' => PostResource::collection(Post::all()),
            'paged' => PostResource::collection(Post::query()->paginate(10)),
            'simple' => PostResource::collection(Post::query()->simplePaginate(10)),
            'cursor' => PostResource::collection(Post::query()->cursorPaginate(10)),
            'comments' => Comment::all()->toResourceCollection(CommentResource::class),
        ]);
    }
}
