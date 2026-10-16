<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Guessed collections as shared data, key for key as ControllerWithResourceSpellings::guessed() renders them. */
final class MiddlewareWithGuessedCollections extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'users' => User::all()->toResourceCollection(),
            'paged_users' => User::query()->paginate(10)->toResourceCollection(),
            'posts' => Post::all()->toResourceCollection(),
            'paged_posts' => Post::query()->simplePaginate(10)->toResourceCollection(),
        ];
    }
}
