<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Cog;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shares the guessed collection DeclinedGuessController renders, key for key. */
final class DeclinedGuessMiddleware extends Middleware
{
    /** Shares the models' guessed resource collection, which the page-prop handler declines. */
    public function share(Request $request): array
    {
        return ['cogs' => Cog::all()->toResourceCollection()];
    }
}
