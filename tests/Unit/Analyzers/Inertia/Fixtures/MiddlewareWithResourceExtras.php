<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\WithMetaResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Shares the resources ResourceExtrasController's show action renders, key for key. */
final class MiddlewareWithResourceExtras extends Middleware
{
    /** Shares a user's card and every user's card with a meta key added, beside a post whose resource adds its own. */
    public function share(Request $request): array
    {
        return [
            'one' => UserCardResource::make(User::query()->firstOrFail())->additional(['meta' => ['n' => 1]]),
            'many' => UserCardResource::collection(User::all())->additional(['meta' => ['n' => 1]]),
            'withed' => WithMetaResource::make(Post::query()->firstOrFail()),
        ];
    }
}
