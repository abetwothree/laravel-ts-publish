<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

class InertiaNamedCollectionsController
{
    /**
     * Tests anonymous paginated result
     *
     * Result should be { posts: JsonResourcePaginator<PostResource> }
     */
    public function resourceAnonymousPaginated(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/ResourceAnonymous', [
            'posts' => PostResource::collection($posts),
        ]);
    }

    /**
     * Tests anonymous result
     *
     * Result should be { posts: AnonymousResourceCollection<PostResource> }
     */
    public function resourceAnonymous(): Response
    {
        $posts = Post::latest()->limit(10)->get();

        return Inertia::render('Collections/ResourceAnonymous', [
            'posts' => PostResource::collection($posts),
        ]);
    }

    /**
     * Test return types with paginated named collection class
     *
     * Result should be { posts: PostCollection & ResourcePagination }
     */
    public function namedCollectionPaginated(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/NamedPaginated', [
            'posts' => new PostCollection($posts),
        ]);
    }

    /**
     * Test return types with named collection class
     *
     * Result should be { posts: PostCollection }
     */
    public function namedCollection(): Response
    {
        $posts = Post::all();

        return Inertia::render('Collections/Named', [
            'posts' => new PostCollection($posts),
        ]);
    }

    /**
     * Test a named collection class with a flat collection (no data $wrap value)
     *
     * Result should be { posts: JsonResourcePaginator<PostResource> }
     */
    public function flatCollectionPaginated(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/FlatPaginated', [
            'posts' => new PostFlatCollection($posts),
        ]);
    }

    /**
     * Test a named collection class with a flat collection (no data $wrap value)
     *
     * Using the `PostFlatCollection` works because its definition is "type PostFlatCollection = PostResource[];"
     *
     * Result should be { posts: PostFlatCollection }
     */
    public function flatCollection(): Response
    {
        $posts = Post::latest()->limit(10)->get();

        return Inertia::render('Collections/Flat', [
            'posts' => new PostFlatCollection($posts),
        ]);
    }
}
