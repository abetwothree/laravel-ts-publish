<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** A real Inertia middleware whose share() casts mark keys optional both ways: a `?` suffix and `optional`. */
class MiddlewareWithOptionalShareCast extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts([
        'filters?' => 'Record<string, string>',
        'locale' => ['type' => "'en' | 'es'", 'optional' => true],
    ])]
    public function share(Request $request): array
    {
        return [
            'filters' => (array) $request->query('filters', []),
            'locale' => 'en',
            'id' => 1,
        ];
    }
}
