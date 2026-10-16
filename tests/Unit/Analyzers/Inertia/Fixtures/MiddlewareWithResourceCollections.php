<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** Shared resource collections, which Inertia sends as their whole response, as it does a page prop. */
final class MiddlewareWithResourceCollections extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'anonymous' => PostResource::collection(Post::all()),
            'named' => new PostCollection(Post::all()),
            'simple' => new PostCollection(Post::query()->simplePaginate(10)),
            'cursor' => PostResource::collection(Post::query()->cursorPaginate(10)),
            'guessed' => Post::query()->paginate(10)->toResourceCollection(),
        ];
    }
}
