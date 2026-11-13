<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller whose casts write a model's name as string literals, beside a page importing that name. */
class LiteralCastController
{
    /** Renders the author under a cast that imports its own `User`. */
    #[TsCasts(['author' => ['type' => 'User', 'import' => '@js/types/ui']])]
    public function module(Post $post): Response
    {
        return Inertia::render('LiteralCast/Module', ['author' => $post->author]);
    }

    /** Renders the author model beside casts whose string literals spell its name and its class. */
    #[TsCasts(['kind' => "'User' | 'Team'", 'morph' => '"App\\\\Models\\\\User"'])]
    public function literal(Post $post): Response
    {
        return Inertia::render('LiteralCast/Literal', [
            'author' => $post->author,
            'kind' => 'User',
            'morph' => 'App\\Models\\User',
        ]);
    }
}
