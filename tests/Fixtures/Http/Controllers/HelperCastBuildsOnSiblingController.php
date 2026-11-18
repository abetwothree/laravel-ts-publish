<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller spreading a props helper whose cast builds on the model the outer props embed. */
class HelperCastBuildsOnSiblingController
{
    public function show(Post $post): Response
    {
        return Inertia::render('HelperCastBuildsOnSibling/Show', [...$this->props(), 'author' => $post->author]);
    }

    /**
     * The props the helper casts.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['k' => ['type' => 'Paginated<User>', 'import' => '@/types/meta']])]
    private function props(): array
    {
        return ['k' => 'x'];
    }
}
