<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Shares a resource and an Inertia prop object, each with a dotted key written under it on some requests. */
class MiddlewareWithClosedDottedHeads
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = ['card' => UserCardResource::make($request->user()), 'always' => Inertia::always(['a' => 1])];

        if ($request->boolean('c')) {
            $shared['card.can'] = true;
            $shared['always.b'] = 2;
        }

        return $shared;
    }
}
