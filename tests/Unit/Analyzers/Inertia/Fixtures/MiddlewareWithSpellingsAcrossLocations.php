<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Casts the `\_x` signature by its exact name on the class and by the single-backslash paste on share(). */
#[TsCasts(['[key: `${string}\\\\_x`]' => 'string'])]
class MiddlewareWithSpellingsAcrossLocations
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['[key: `${string}\\_x`]' => 'number'])]
    public function share(Request $request): array
    {
        $data = ['id' => 1];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_x"] = 'x';
        }

        return $data;
    }
}
