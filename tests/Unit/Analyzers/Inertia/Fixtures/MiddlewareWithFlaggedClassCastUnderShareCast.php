<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** The class marks `held` optional; share() retypes it and says nothing about `optional`. */
#[TsCasts(['held' => ['type' => 'Held', 'optional' => true]])]
class MiddlewareWithFlaggedClassCastUnderShareCast
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['held' => 'HeldShare'])]
    public function share(Request $request): array
    {
        return ['held' => 1, 'id' => 1];
    }
}
