<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Requests\UpdatePostRequest;

/**
 * Page casts on a numeric key, under a losing spelling, over two components only one of which returns the key, and
 * with or without an `optional` flag; and a prop typed from a request's import-aware cast.
 */
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

    /** Marks a prop and an added signature optional by the cast's own flag. */
    #[TsCasts([
        'heading' => ['type' => 'Heading', 'optional' => true],
        '[key: `${string}_note`]' => ['type' => 'number', 'optional' => true],
    ])]
    public function optionalCast(): Response
    {
        return Inertia::render('Edges/OptionalCast', ['heading' => 'x', 'id' => 1]);
    }

    /** Casts two props only one ternary arm sets: one says nothing about `optional`, the other clears it. */
    #[TsCasts(['meta' => 'PageMeta', 'note' => ['type' => 'Note', 'optional' => false]])]
    public function typeOnlyCast(): Response
    {
        return Inertia::render('Edges/TypeOnlyCast', request()->has('a') ? ['meta' => [], 'note' => 'x', 'id' => 1] : ['id' => 2]);
    }

    /** UpdatePostRequest casts `attributes` to an imported type. */
    public function validated(UpdatePostRequest $request): Response
    {
        return Inertia::render('Edges/Validated', ['attributes' => $request->validated('attributes')]);
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
