<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MergingWithResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\Post;

/** Shares a post's resource whose `with()` holds `merge()` and `mergeWhen()` values. */
final class MiddlewareWithMergingWith extends Middleware
{
    /** Shares the first post's resource. */
    public function share(Request $request): array
    {
        return ['withMerge' => MergingWithResource::make(Post::query()->firstOrFail())];
    }
}
