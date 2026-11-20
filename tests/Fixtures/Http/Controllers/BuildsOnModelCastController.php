<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller whose cast builds on the model its key's value embeds, beside a name only it imports. */
class BuildsOnModelCastController
{
    #[TsCasts(['meta' => ['type' => '{ author: User | null; tag: Tag }', 'import' => '@/types/tag']])]
    public function show(Post $post): Response
    {
        return Inertia::render('BuildsOnModelCast/Show', ['meta' => ['author' => $post->author]]);
    }
}
