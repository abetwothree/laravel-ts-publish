<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\UserResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** JSON:API resources as shared data, key for key as ControllerWithResourceSpellings::jsonApi() renders them. */
final class MiddlewareWithJsonApiResources extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'post' => new PostResource(Post::query()->firstOrFail()),
            'author' => UserResource::make(User::query()->firstOrFail()),
            'posts' => PostResource::collection(Post::all()),
        ];
    }
}
