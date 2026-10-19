<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** A share() that builds its props in a local variable and returns it. */
final class MiddlewareWithReturnedVariable extends Middleware
{
    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $shared = array_merge(parent::share($request), [
            'appName' => (string) config('app.name'),
        ]);

        if ($request->user() !== null) {
            $shared['userId'] = (int) $request->user()->getAuthIdentifier();
        }

        return $shared;
    }
}
