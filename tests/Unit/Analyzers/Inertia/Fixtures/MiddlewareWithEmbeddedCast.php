<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props whose cast spells, with no import, the model its key's value embeds. */
final class MiddlewareWithEmbeddedCast extends Middleware
{
    #[TsCasts(['auth' => '{ user: User | null }'])]
    public function share(Request $request): array
    {
        return ['auth' => ['user' => $request->user()]];
    }
}
