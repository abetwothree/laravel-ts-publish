<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props whose cast, with an import, builds on the model its key's value embeds beside a name only it spells. */
final class MiddlewareWithNestedNameCast extends Middleware
{
    #[TsCasts(['auth' => ['type' => '{ user: User | null; meta: Meta }', 'import' => '@/types/meta']])]
    public function share(Request $request): array
    {
        return ['auth' => ['user' => $request->user()]];
    }
}
