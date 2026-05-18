<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

class InertiaPaginationsController
{
    /**
     * Test page type is { posts: LengthAwarePaginator<Post> }
     */
    public function lengthAware(): Response
    {
        $posts = Post::latest()->paginate(25);

        return Inertia::render('Collections/Index', [
            'posts' => $posts,
        ]);
    }

    /**
     * Test page type is { posts: SimplePaginator<Post> }
     */
    public function simple(): Response
    {
        $posts = Post::latest()->simplePaginate(25);

        return Inertia::render('Collections/Simple', [
            'posts' => $posts,
        ]);
    }

    /**
     * Test page type is { posts: CursorPaginator<Post> }
     */
    public function cursor(): Response
    {
        $posts = Post::latest()->cursorPaginate(25);

        return Inertia::render('Collections/Cursor', [
            'posts' => $posts,
        ]);
    }
}
