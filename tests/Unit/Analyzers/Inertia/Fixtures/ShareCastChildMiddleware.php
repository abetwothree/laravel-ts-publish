<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Spreads its parent's share(), whose own cast only the engine applies: the shared-data analyzer reads this one's. */
class ShareCastChildMiddleware extends ShareCastBaseMiddleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [...parent::share($request), 'id' => 1];
    }
}
