<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** A real Inertia middleware whose share() marks `filters` optional by a `?` cast key, for subclasses to read. */
class OptionalCastBaseMiddleware extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['filters?' => 'string[]'])]
    public function share(Request $request): array
    {
        return ['filters' => null, 'app' => 'x'];
    }
}
