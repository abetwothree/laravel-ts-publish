<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/**
 * parseDocblockReturnArrayShape() folds the optional marker into the map key ('filters?'), so an
 * override lookup by plain prop name misses and the prop gets emitted twice. `appName` additionally
 * proves a #[TsCasts] entry still wins over the optional docblock entry for the same key.
 */
#[TsCasts(['appName' => 'AppName'])]
class MiddlewareWithOptionalDocblockKey
{
    /**
     * @return array{
     *      appName?: string,
     *      filters?: array<string, string>
     *  }
     */
    public function share(Request $request): array
    {
        return [
            'appName' => 1,
            'filters' => ['q' => 'search'],
        ];
    }
}
