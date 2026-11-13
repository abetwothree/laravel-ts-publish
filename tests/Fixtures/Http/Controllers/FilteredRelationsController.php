<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** An Inertia action rendering attribute filters over models, a bound model's relation and a held model's relation. */
class FilteredRelationsController
{
    /** Holds the post whose relation the action filters through `$this`. */
    public function __construct(private readonly Post $post) {}

    /** Renders `map->only()` and `map->except()` filters over every post and over each post's comments. */
    public function show(Post $post): Response
    {
        $posts = Post::all();

        return Inertia::render('FilteredRelations/Show', [
            'posts' => Post::all()->map->only(['id', 'title']),
            'trimmed' => $posts->map->except(['content', 'created_at', 'updated_at']),
            'comments' => $post->comments->map->only(['id', 'content']),
            'held' => $this->post->comments->map->only(['id']),
        ]);
    }
}
