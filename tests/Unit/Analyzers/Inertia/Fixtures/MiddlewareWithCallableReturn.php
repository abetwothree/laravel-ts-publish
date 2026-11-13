<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shares an arrow function returning `??` over a local closure, which Inertia calls once. */
class MiddlewareWithCallableReturn extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $f = fn (): ?int => $request->boolean('x') ? 1 : null;

        return ['a' => fn () => $f ?? 5];
    }
}
