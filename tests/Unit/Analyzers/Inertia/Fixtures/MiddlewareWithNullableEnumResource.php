<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostVisibilitiesResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\Post;

/** Shares an enum resource over the first post's nullable visibility, alone and beside a resource's response. */
final class MiddlewareWithNullableEnumResource extends Middleware
{
    /** Shares the wrap, and the response whose `with()` and `additional()` wrap it again. */
    public function share(Request $request): array
    {
        $post = Post::query()->firstOrFail();

        return [
            'visibility' => EnumResource::make($post->visibility),
            'card' => PostVisibilitiesResource::make($post)->additional(['extra' => EnumResource::make($post->visibility)]),
        ];
    }
}
