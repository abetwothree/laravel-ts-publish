<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Replaces its parent's share() without reading it, so the parent's `filters?` cast types nothing it sends. */
class UnreadParentShareMiddleware extends OptionalCastBaseMiddleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['own' => 1];
    }
}
