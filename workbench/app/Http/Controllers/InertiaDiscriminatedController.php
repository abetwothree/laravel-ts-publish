<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\TrackingCode;

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

    /**
     * Props read back as keys: a resource's `additional()` keys that follow a sibling key, dotted keys written into a
     * prop whose keys do, beside or over its keys; an enum resource beside such keys; dotted keys under a union holding
     * an object, which Inertia replaces with `[]`; and a collection of such keys or of null.
     */
    public function readBack(Post $post): Response
    {
        return Inertia::render('Posts/DiscriminatedReadBack', [
            'post' => PostResource::make($post)->additional([
                'pinned' => $post->is_pinned,
                'v' => $post->is_pinned ? $post->title : null,
            ]),
            'placement' => ['pinned' => $post->is_pinned, 'at' => $post->is_pinned ? $post->updated_at : null],
            'placement.extra' => 1,
            'entered' => ['pinned' => $post->is_pinned, 'at' => $post->is_pinned ? $post->updated_at : null],
            'entered.at' => 'x',
            'wrap' => [
                'pinned' => $post->is_pinned,
                'until' => $post->is_pinned ? $post->updated_at : null,
                'e' => EnumResource::make($post->status),
            ],
            'tracked' => $post->is_pinned ? new TrackingCode('x') : ['code' => 1, 'carrier' => 'ups'],
            'tracked.extra' => 1,
            'cast' => $post->is_pinned ? (object) ['a' => 1] : ['a' => 'x'],
            'cast.b' => 1,
            'collected' => collect($this->pinnedMeta($post)),
        ]);
    }

    /**
     * Whether the post is pinned, with its title only when it is, or null for a post not saved yet.
     *
     * @return array<string, mixed>|null
     */
    private function pinnedMeta(Post $post): ?array
    {
        if ($post->id < 1) {
            return null;
        }

        return ['pinned' => $post->is_pinned, 'v' => $post->is_pinned ? $post->title : null];
    }
}
