<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Renders props read through the signed-in user, in each spelling Laravel offers for the default and a named guard. */
class SignedInUserController
{
    /** Renders the user through `auth()`, `Auth` and `request()`, beside the injected request and a named guard. */
    public function show(Request $request): Response
    {
        return Inertia::render('SignedIn/Show', [
            'helper' => auth()->user()?->name,
            'facade' => Auth::user()?->email,
            'request' => request()->user()?->name,
            'user' => Auth::user(),
            'injected' => $request->user()?->name,
            'named' => auth('admin')->user()?->name,
        ]);
    }

    /** Renders reads past the user with no `?->`, a relation, the default guard by name, and a named guard. */
    public function steps(): Response
    {
        return Inertia::render('SignedIn/Steps', [
            'helper' => auth()->user()->name,
            'facade' => Auth::user()->email,
            'request' => request()->user()->name,
            'posts' => auth()->user()->posts()->count(),
            'maybePosts' => Auth::user()?->posts()->count(),
            'guard' => Auth::guard()->user()?->name,
            'helperGuard' => auth()->guard()->user()?->name,
            'namedGuard' => Auth::guard('admin')->user()?->name,
            'namedRequest' => request()->user('admin')?->name,
        ]);
    }

    /** Renders the user through the spellings that read whichever guard a route's guard middleware made the default. */
    public function routeGuard(): Response
    {
        return Inertia::render('SignedIn/RouteGuard', [
            'helper' => auth()->user()?->name,
            'facade' => Auth::user()?->name,
            'request' => request()->user()?->name,
            'guard' => Auth::guard()->user()?->name,
            'user' => request()->user(),
        ]);
    }
}
