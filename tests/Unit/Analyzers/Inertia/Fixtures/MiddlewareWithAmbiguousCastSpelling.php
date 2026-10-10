<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Its one cast key spells both of the shared data's backslash signatures' names. */
#[TsCasts(['[key: `${string}\\\\r`]' => 'number'])]
class MiddlewareWithAmbiguousCastSpelling
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $data = ['id' => 1];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\\r"] = 1;
            $data["{$name}\\\\r"] = 1;
        }

        return $data;
    }
}
