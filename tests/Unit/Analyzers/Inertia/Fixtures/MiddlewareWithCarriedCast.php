<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props whose cast displaces a model it does not spell, beside a cast spelling it over an untyped value. */
final class MiddlewareWithCarriedCast extends Middleware
{
    /** Shares the signed-in user twice, under casts only one of which names its model. */
    #[TsCasts(['meta' => 'Record<string, unknown>', 'owner' => 'User | null'])]
    public function share(Request $request): array
    {
        return ['meta' => ['author' => $request->user()], 'owner' => request()->user('web')];
    }
}
