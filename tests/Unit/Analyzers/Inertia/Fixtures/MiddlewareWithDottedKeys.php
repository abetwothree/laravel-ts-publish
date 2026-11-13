<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Shares two dotted keys Inertia unpacks, one of them numeric. */
class MiddlewareWithDottedKeys
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['auth.user' => 'x', '1.5' => 'y'];
    }
}
