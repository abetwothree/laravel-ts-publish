<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;

/** Page casts on a numeric key, under a losing spelling, and over two components only one of which returns the key. */
class ControllerWithCastKeyEdges
{
    /** Casts the numeric key `42`, which PHP stores as an int. */
    #[TsCasts(['heading' => 'string', '42' => 'boolean'])]
    public function numeric(): Response
    {
        return Inertia::render('Edges/Numeric', ['heading' => 'x', 42 => 1]);
    }

    /** Casts the `\_cast` signature under both spellings; the losing one names an import. */
    #[TsCasts([
        '[key: `${string}\\\\_cast`]' => 'number',
        '[key: `${string}\\_cast`]' => ['type' => 'Loser', 'import' => '@/types/loser'],
    ])]
    public function losing(): Response
    {
        return Inertia::render('Edges/Losing', [...$this->keys(), 'id' => 1]);
    }

    /** Renders two components; only the first returns the key the cast names. */
    #[TsCasts(['meta' => ['type' => 'PageMeta', 'import' => '@/types/meta']])]
    public function twoComponents(): Response
    {
        if (request()->has('a')) {
            return Inertia::render('Edges/WithMeta', ['meta' => [], 'id' => 1]);
        }

        return Inertia::render('Edges/WithoutMeta', ['id' => 2]);
    }

    /**
     * Keys whose literal text holds a backslash.
     *
     * @return array<string, string>
     */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_cast"] = 'x';
        }

        return $data;
    }
}
