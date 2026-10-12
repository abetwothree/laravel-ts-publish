<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Inertia actions that reassign their route-bound model parameter before rendering. */
class ReassignedRouteModelController
{
    /** Reassigns the bound post to its author; a User has no `title`, so Laravel sends null for it. */
    public function show(Post $post): Response
    {
        $post = $post->author;

        return Inertia::render('Reassigned/Show', [
            'title' => $post->title,
            'name' => $post->name,
            'bare' => $post,
        ]);
    }

    /** Reassigns the bound post in a branch, to a copy of itself. */
    public function branch(Post $post): Response
    {
        if ($post->id > 100) {
            $post = $post->replicate();
        }

        return Inertia::render('Reassigned/Branch', [
            'title' => $post->title,
        ]);
    }

    /** Reassigns the bound post to its own fresh copy. */
    public function fresh(Post $post): Response
    {
        $post = $post->fresh() ?? $post;

        return Inertia::render('Reassigned/Fresh', [
            'title' => $post->title,
        ]);
    }
}
