<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

#[TsCasts([
    'auth' => ['type' => 'SharedData', 'import' => '@js/types/shared'],
    'flash' => ['type' => 'SharedData', 'import' => '@js/types/shared'],
    'appName' => 'string',
])]
class MiddlewareWithDuplicateImports
{
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [];
    }
}
