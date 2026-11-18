<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/**
 * Page props that follow a sibling prop's value (GH-157): a top-level pair, which stays two plain props, and the same
 * pair inside a nested prop.
 */
class InertiaDiscriminatedController
{
    public function show(Post $post): Response
    {
        return Inertia::render('Posts/Discriminated', [
            'pinned' => $post->is_pinned,
            'pinned_title' => $post->is_pinned ? $post->title : null,
            'placement' => [
                'pinned' => $post->is_pinned,
                'at' => $post->is_pinned ? $post->updated_at : $post->published_at,
                'rank' => $post->is_pinned ? $post->priority : null,
            ],
        ]);
    }
}
