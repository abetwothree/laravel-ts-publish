<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Handover;
use Workbench\App\Models\Post;

/** Renders a route-bound model after `load()` or `loadMissing()`, in each argument spelling Laravel reads. */
class LoadedRelationsController
{
    /** Renders the post with one relation loaded by name. */
    public function one(Post $post): Response
    {
        return Inertia::render('Loaded/One', ['loaded' => $post->load('author')]);
    }

    /** Renders the post with a list of relations loaded. */
    public function many(Post $post): Response
    {
        return Inertia::render('Loaded/Many', ['loaded' => $post->load(['author', 'comments'])]);
    }

    /** Renders the post with a relation of a relation loaded through a dotted path. */
    public function nested(Post $post): Response
    {
        return Inertia::render('Loaded/Nested', ['loaded' => $post->load('author.posts')]);
    }

    /** Renders the post with a relation loaded under a constraint that keeps its row. */
    public function constrained(Post $post): Response
    {
        return Inertia::render('Loaded/Constrained', [
            'loaded' => $post->load(['author' => fn (Builder $query) => $query->withCount('posts')]),
        ]);
    }

    /** Renders the post with a relation loaded with two of its columns. */
    public function columns(Post $post): Response
    {
        return Inertia::render('Loaded/Columns', ['loaded' => $post->load('author:id,name')]);
    }

    /** Renders the post with a relation loaded only where it is missing. */
    public function missing(Post $post): Response
    {
        return Inertia::render('Loaded/Missing', ['loaded' => $post->loadMissing('author')]);
    }

    /** Renders the post with a camel-cased relation loaded, which Laravel sends under its snake-cased name. */
    public function camel(Post $post): Response
    {
        return Inertia::render('Loaded/Camel', ['loaded' => $post->load('categoryRel')]);
    }

    /** Renders the post with a to-one relation loaded under a constraint that may filter its row out. */
    public function filtered(Post $post): Response
    {
        return Inertia::render('Loaded/Filtered', [
            'loaded' => $post->load(['author' => function (Builder $query): void {
                $query->where('name', 'Grace');
            }]),
        ]);
    }

    /** Renders the post with a list relation loaded under a constraint, and one loaded with columns a closure picks. */
    public function listed(Post $post): Response
    {
        return Inertia::render('Loaded/Listed', [
            'loaded' => $post->load([
                'comments' => fn (Builder $query) => $query->where('content', 'alpha')->latest(),
                'author' => fn (Builder $query) => $query->select('id', 'email'),
            ]),
        ]);
    }

    /** Renders the post with relations named as several string arguments. */
    public function variadic(Post $post): Response
    {
        return Inertia::render('Loaded/Variadic', ['loaded' => $post->load('author', 'comments')]);
    }

    /** Renders the post with a relation of a relation named in a nested array, with columns. */
    public function nestedArray(Post $post): Response
    {
        return Inertia::render('Loaded/NestedArray', ['loaded' => $post->load(['author' => ['posts:id,user_id,title']])]);
    }

    /** Renders the post with a relation loaded with columns, and its own relation through a dotted path. */
    public function nestedColumns(Post $post): Response
    {
        return Inertia::render('Loaded/NestedColumns', ['loaded' => $post->loadMissing(['author:id,name', 'author.posts'])]);
    }

    /** Renders relations loaded on a found or queried post and the signed-in user, and by names held in a local. */
    public function receivers(Post $post): Response
    {
        $relations = ['author'];

        return Inertia::render('Loaded/Receivers', [
            'found' => Post::find(1)?->load('author'),
            'queried' => Post::query()->whereKey($post->id)->firstOrFail()->loadMissing('comments'),
            'viewer' => auth()->user()?->load('posts'),
            'held' => $post->load($relations),
            'counted' => $post->loadCount('comments'),
        ]);
    }

    /** Renders relations loaded on every model of a query's collection and of a loaded relation's. */
    public function collections(Post $post): Response
    {
        return Inertia::render('Loaded/Collections', [
            'posts' => Post::query()->get()->load('author'),
            'comments' => $post->comments->loadMissing('user'),
        ]);
    }

    /** Renders a handover with its relations to two models of one name loaded. */
    public function sameNamed(Handover $handover): Response
    {
        return Inertia::render('Loaded/SameNamed', ['loaded' => $handover->load('sender', 'receiver')]);
    }
}
