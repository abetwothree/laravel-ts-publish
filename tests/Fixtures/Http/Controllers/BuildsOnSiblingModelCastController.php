<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller whose cast builds on the model another key embeds, beside a name only it imports. */
class BuildsOnSiblingModelCastController
{
    #[TsCasts(['k' => ['type' => '{ owner: User | null; meta: Meta }', 'import' => '@/types/meta']])]
    public function show(Post $post): Response
    {
        return Inertia::render('BuildsOnSiblingModelCast/Show', ['k' => 'x', 'author' => $post->author]);
    }
}
