<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Renders finder chains on the user, which a run's published set may leave out: in a ternary and as a collection. */
class UnpublishedFinderController
{
    /** Renders a found user or null, and a page of users as their resource collection. */
    public function show(Post $post): Response
    {
        return Inertia::render('Unpublished/Finder', [
            'found' => $post->is_pinned ? User::find(1) : null,
            'collected' => User::query()->paginate(10)->toResourceCollection(),
        ]);
    }
}
