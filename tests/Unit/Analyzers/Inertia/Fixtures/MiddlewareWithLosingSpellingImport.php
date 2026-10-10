<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** Casts the `\_cast` signature under both spellings; the losing single-backslash one names an import. */
#[TsCasts([
    '[key: `${string}\\\\_cast`]' => 'number',
    '[key: `${string}\\_cast`]' => ['type' => 'Loser', 'import' => '@/types/loser'],
])]
class MiddlewareWithLosingSpellingImport
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
            $data["{$name}\\_cast"] = 'x';
        }

        return $data;
    }
}
