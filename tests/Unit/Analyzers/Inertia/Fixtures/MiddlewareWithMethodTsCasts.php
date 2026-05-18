<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

class MiddlewareWithMethodTsCasts
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts([
        'appName' => 'string',
        'userId' => 'number',
    ])]
    public function share(Request $request): array
    {
        return [];
    }
}
