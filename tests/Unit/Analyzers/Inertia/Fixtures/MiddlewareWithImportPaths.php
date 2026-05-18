<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

#[TsCasts([
    'auth' => ['type' => 'AuthData', 'import' => '@js/types/auth'],
    'flash' => ['type' => 'FlashData', 'import' => '@js/types/flash'],
    'appName' => 'string',
])]
class MiddlewareWithImportPaths
{
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [];
    }
}
