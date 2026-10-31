<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostTableCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostTableCountResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\DocblockPostTable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostQueryTable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostTable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\UserQueryPostTable;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Renders Inertia UI Tables beside other props, under a cast, in a local, inside a resolved resource, and counted. */
class InertiaTableBesidePropsController
{
    /** Renders a table beside two plain props, one of them retyped by the action's cast. */
    #[TsCasts(['heading' => '"fixed"'])]
    public function index(): Response
    {
        return Inertia::render('Tables/Beside', ['posts' => PostTable::make(), 'heading' => 'x', 'count' => 3]);
    }

    /** Renders a table a local holds and a `new` of one, each beside a plain prop. */
    public function held(): Response
    {
        $table = PostTable::make()->defaultSort('-id');

        return Inertia::render('Tables/Held', ['posts' => $table, 'drafts' => new PostTable, 'count' => 3]);
    }

    /** Renders the count of a table's query, a chain that ends past the table, in a literal and through a local. */
    public function counted(): Response
    {
        $held = PostQueryTable::make()->query()->count();

        return Inertia::render('Tables/Counted', ['n' => PostQueryTable::make()->query()->count(), 'held' => $held]);
    }

    /** Renders the rows and the first row of a table's query. */
    public function queried(): Response
    {
        return Inertia::render('Tables/Queried', [
            'rows' => PostQueryTable::make()->query()->get(),
            'first' => PostQueryTable::make()->query()->latest('id')->first(),
        ]);
    }

    /** Renders a props local whose key holds the count of a table's query. */
    public function countedLocal(): Response
    {
        $props = ['h' => 'x'];
        $props['n'] = PostQueryTable::make()->query()->count();

        return Inertia::render('Tables/CountedLocal', $props);
    }

    /** Renders a table query's plucked ids, inline and in a local, and the query itself, which no handler reads. */
    public function plucked(): Response
    {
        $held = PostQueryTable::make()->query()->pluck('id');

        return Inertia::render('Tables/Plucked', [
            'ids' => PostQueryTable::make()->query()->pluck('id'),
            'query' => PostQueryTable::make()->query(),
            'held' => $held,
        ]);
    }

    /** Renders the rows and the first row of the query of a table whose `query()` names another model than its own. */
    public function mixedQueried(): Response
    {
        return Inertia::render('Tables/MixedQueried', [
            'rows' => UserQueryPostTable::make()->query()->get(),
            'first' => UserQueryPostTable::make()->query()->first(),
        ]);
    }

    /** Renders tables whose untyped methods return them by a docblock's short name and alias. */
    public function docblocked(): Response
    {
        return Inertia::render('Tables/Docblocked', [
            'short' => DocblockPostTable::make()->fluentShort(),
            'aliased' => DocblockPostTable::make()->fluentAliased(),
        ]);
    }

    /** Renders a docblock-typed untyped table method that returns no table. */
    public function docblockTotal(): Response
    {
        return Inertia::render('Tables/DocblockTotal', ['total' => DocblockPostTable::make()->total()]);
    }

    /** Renders a resolved resource whose body holds the count of a table's query. */
    public function countedResolved(Post $post): Response
    {
        return Inertia::render('Tables/CountedResolved', ['card' => PostTableCountResource::make($post)->resolve()]);
    }

    /** Renders a resolved resource whose body holds a table, which Inertia sends as its `toArray()`. */
    public function resolved(Post $post): Response
    {
        return Inertia::render('Tables/Resolved', ['card' => PostTableCardResource::make($post)->resolve()]);
    }
}
