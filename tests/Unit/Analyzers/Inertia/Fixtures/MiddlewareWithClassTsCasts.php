<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** `appName` infers as number here, so the string override can only come from the attribute. */
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
        return [
            'appName' => 1,
            'userId' => 2,
        ];
    }
}
