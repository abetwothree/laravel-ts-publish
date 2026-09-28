<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;

/** Pages whose cast key spells a signature's published name otherwise: pasted into single quotes, or with a raw CR. */
class ControllerWithSignatureCastSpelling
{
    /** The cast retypes the `\_cast` signature. */
    #[TsCasts(['[key: `${string}\\_cast`]' => 'number'])]
    public function show(): Response
    {
        return Inertia::render('Escaped/Show', [...$this->keys(), 'id' => 1]);
    }

    /** The cast, a double-quoted PHP string, holds a raw CR where the published name writes `\r`. */
    #[TsCasts(["[key: `\${string}\r`]" => 'number'])]
    public function rawCr(): Response
    {
        return Inertia::render('Escaped/RawCr', [...$this->crKeys(), 'id' => 1]);
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

    /** Keys whose literal text ends in a CR. */
    public function crKeys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\r"] = 'x';
        }

        return $data;
    }
}
