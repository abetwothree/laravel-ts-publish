<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Casts the `_flag` signature optional with a trailing `?`, which a signature cannot carry. */
#[TsCasts(['[key: `${string}_flag`]?' => 'boolean'])]
class MiddlewareWithOptionalSignatureCast
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
            $data["{$name}_flag"] = true;
        }

        return $data;
    }
}
