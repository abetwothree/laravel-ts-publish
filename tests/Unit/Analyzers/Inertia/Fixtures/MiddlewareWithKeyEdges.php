<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Shares a key that is not an identifier, so the shared-data type must quote it. */
class MiddlewareWithKeyEdges
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['can-edit' => true, 'ok' => 1];
    }
}
