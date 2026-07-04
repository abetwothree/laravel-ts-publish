<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

#[TsCasts([
    'appName' => 'string',
    'flash' => '{ success: string | null, error: string | null }',
])]
class MiddlewareWithClassTsCasts
{
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [];
    }
}
