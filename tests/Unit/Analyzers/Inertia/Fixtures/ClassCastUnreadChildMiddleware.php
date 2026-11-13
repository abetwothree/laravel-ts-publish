<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Replaces the share() of a parent with a class-level cast without reading it, so that cast types nothing it sends. */
class ClassCastUnreadChildMiddleware extends ClassCastBaseMiddleware
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
