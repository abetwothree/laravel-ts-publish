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

    /** Fills an absent post with a new one, which is still a Post. */
    public function coalesceAssign(?Post $post = null): Response
    {
        $post ??= new Post;

        return Inertia::render('Reassigned/CoalesceAssign', [
            'title' => $post->title,
        ]);
    }

    /** Reassigns the bound post to its author, then passes it by name. */
    public function compacted(Post $post): Response
    {
        $post = $post->author;

        return Inertia::render('Reassigned/Compacted', compact('post'));
    }

    /** Reassigns a copy of the bound post that a closure captures by value, leaving the action's own post as it is. */
    public function capturedByValue(Post $post): Response
    {
        $author = function () use ($post) {
            $post = $post->author;

            return $post;
        };

        return Inertia::render('Reassigned/CapturedByValue', [
            'title' => $post->title,
        ]);
    }

    /** Reassigns the bound post through a closure that captures it by reference. */
    public function capturedByReference(Post $post): Response
    {
        $author = function () use (&$post) {
            $post = $post->author;

            return $post;
        };

        return Inertia::render('Reassigned/CapturedByReference', [
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
