<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Its cast key is pasted from a backslash signature's published name into a single-quoted PHP string. */
#[TsCasts(['[key: `${string}\\_cast`]' => 'number'])]
class MiddlewareWithSignatureCastSpelling
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [...$this->keys()];
    }

    /** Keys whose literal text holds a backslash. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_cast"] = 'x';
        }

        return $data;
    }
}
