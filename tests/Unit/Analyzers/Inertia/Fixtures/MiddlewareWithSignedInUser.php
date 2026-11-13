<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

/** Shares the signed-in user read through `auth()`, `Auth` and `request()`. */
class MiddlewareWithSignedInUser extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'helper' => auth()->user()?->name,
            'facade' => Auth::user()?->email,
            'request' => request()->user()?->name,
            'user' => request()->user(),
        ];
    }
}
