<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Shares the int key `42` at the top level, and `7` inside a nested array. */
class MiddlewareWithNumericBodyKey
{
    /**
     * The shared props.
     *
     * @return array<array-key, mixed>
     */
    public function share(Request $request): array
    {
        return ['appName' => 'x', 42 => true, 'codes' => [7 => 'a']];
    }
}
