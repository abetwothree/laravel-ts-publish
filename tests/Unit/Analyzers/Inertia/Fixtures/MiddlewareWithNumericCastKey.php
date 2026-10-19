<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Casts the numeric key `42`, which PHP stores as an int. */
#[TsCasts(['appName' => 'string', '42' => 'boolean'])]
class MiddlewareWithNumericCastKey
{
    /**
     * The shared props.
     *
     * @return array<array-key, mixed>
     */
    public function share(Request $request): array
    {
        return ['appName' => 'x', 42 => 1];
    }
}
