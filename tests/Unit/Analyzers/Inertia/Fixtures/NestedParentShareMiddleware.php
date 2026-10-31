<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Shares its parent's share() under a key of its own, so none of the parent's keys reaches the top level. */
class NestedParentShareMiddleware extends OptionalCastBaseMiddleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['nested' => parent::share($request), 'child' => 1];
    }
}
