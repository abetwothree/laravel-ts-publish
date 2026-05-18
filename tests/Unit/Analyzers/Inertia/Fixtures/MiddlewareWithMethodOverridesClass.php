<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

#[TsCasts([
    'appName' => 'string',
    'flash' => 'FlashData',
])]
class MiddlewareWithMethodOverridesClass
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts([
        'flash' => '{ success: string | null, error: string | null }',
    ])]
    public function share(Request $request): array
    {
        return [];
    }
}
