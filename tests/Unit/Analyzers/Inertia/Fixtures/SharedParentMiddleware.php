<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** The base half of the parent-chain fixture: its keys must survive into the child's shape. */
class SharedParentMiddleware extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => $request->string('locale'),
            'theme' => 'light',
        ];
    }
}
