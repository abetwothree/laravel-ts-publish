<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** A real Inertia middleware whose class-level cast retypes `flag`, which PHP never lets a subclass inherit. */
#[TsCasts(['flag' => 'boolean'])]
class ClassCastBaseMiddleware extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['flag' => 1];
    }
}
