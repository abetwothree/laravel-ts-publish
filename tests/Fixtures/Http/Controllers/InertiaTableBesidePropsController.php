<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostTableCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostTable;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** Renders Inertia UI Tables beside other props, under a cast, in a local and inside a resolved resource. */
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

    /** Renders a resolved resource whose body holds a table, which Inertia sends as its `toArray()`. */
    public function resolved(Post $post): Response
    {
        return Inertia::render('Tables/Resolved', ['card' => PostTableCardResource::make($post)->resolve()]);
    }
}
