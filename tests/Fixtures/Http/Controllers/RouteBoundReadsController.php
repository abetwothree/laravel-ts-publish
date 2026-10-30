<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** An Inertia action rendering reads and Eloquent calls on its route-bound model. */
class RouteBoundReadsController
{
    /** Renders an enum resource of a cast, relation counts and a refreshed copy of the bound post. */
    public function show(Post $post): Response
    {
        return Inertia::render('RouteBoundReads/Show', [
            'status' => EnumResource::make($post->status),
            'authorPosts' => $post->author->posts()->count(),
            'comments' => $post->comments()->count(),
            'fresh' => $post->fresh(),
        ]);
    }
}
