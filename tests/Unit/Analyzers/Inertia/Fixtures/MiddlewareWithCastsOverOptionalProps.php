<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/**
 * Casts over props only one branch shares: type-only, under a docblock entry without `?`, and `optional => false`; a
 * type-only share() cast over the class's `held?`; and one adding a signature by its `optional` flag.
 */
#[TsCasts([
    'flash' => 'Flash',
    'notice' => 'Notice',
    'banner' => ['type' => 'Banner', 'optional' => false],
    'held?' => 'Held',
    '[key: `${string}_note`]' => ['type' => 'number', 'optional' => true],
])]
class MiddlewareWithCastsOverOptionalProps
{
    /**
     * The shared props.
     *
     * @return array{notice: string}
     */
    #[TsCasts(['held' => 'HeldShare'])]
    public function share(Request $request): array
    {
        if ($request->has('a')) {
            return ['flash' => 'x', 'notice' => 'y', 'banner' => 'z', 'held' => 1, 'id' => 1];
        }

        return ['held' => 2, 'id' => 2];
    }
}
