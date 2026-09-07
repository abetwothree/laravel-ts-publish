<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** The array_merge() spelling of the starter-kit share(); it must produce the identical shape. */
final class StarterKitArrayMergeMiddleware extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => ['user' => $request->user()],
            'ziggy' => fn () => ['location' => $request->url()],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ]);
    }
}
