<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props whose cast spells, with no import, the model its key's value holds. */
final class MiddlewareWithImportlessCast extends Middleware
{
    /** Shares the signed-in user under a cast that names its model. */
    #[TsCasts(['owner' => 'User | null'])]
    public function share(Request $request): array
    {
        return ['owner' => $request->user()];
    }
}
