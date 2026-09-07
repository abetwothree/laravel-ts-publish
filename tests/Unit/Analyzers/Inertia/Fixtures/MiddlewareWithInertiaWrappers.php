<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

/** Inertia v2 wrappers: defer/optional/lazy are absent from a partial reload, always/merge are not. */
class MiddlewareWithInertiaWrappers extends Middleware
{
    public function share(Request $request): array
    {
        return [
            'notifications' => Inertia::defer(fn () => ['count' => 3]),
            'permissions' => Inertia::optional(fn () => $request->boolean('admin')),
            'locale' => Inertia::always($request->string('locale')),
            'appName' => Inertia::merge(fn () => config('app.name')),
        ];
    }
}
