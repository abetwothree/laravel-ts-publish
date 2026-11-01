<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\Post;

/** Shares an enum resource over the first post's nullable visibility. */
final class MiddlewareWithNullableEnumResource extends Middleware
{
    /** Shares the wrap. */
    public function share(Request $request): array
    {
        return [
            'visibility' => EnumResource::make(Post::query()->firstOrFail()->visibility),
        ];
    }
}
