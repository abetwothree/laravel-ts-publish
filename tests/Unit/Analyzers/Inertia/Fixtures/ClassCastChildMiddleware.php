<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Spreads the share() of a parent whose cast sits on the class, not on the method. */
class ClassCastChildMiddleware extends ClassCastBaseMiddleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [...parent::share($request)];
    }
}
