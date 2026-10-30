<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller whose cast spells, with no import, the model its key's value embeds. */
class EmbeddedCastController
{
    #[TsCasts(['meta' => '{ author: User | null }'])]
    public function show(Post $post): Response
    {
        return Inertia::render('EmbeddedCast/Show', ['meta' => ['author' => $post->author]]);
    }
}
