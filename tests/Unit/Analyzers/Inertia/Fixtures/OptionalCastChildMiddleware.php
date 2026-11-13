<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Spreads its parent's share(), whose `filters?` cast it declares none of. */
class OptionalCastChildMiddleware extends OptionalCastBaseMiddleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [...parent::share($request), 'child' => 1];
    }
}
