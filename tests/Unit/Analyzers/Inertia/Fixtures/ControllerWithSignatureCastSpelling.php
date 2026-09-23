<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;

/** A page whose cast key is pasted from a backslash signature's published name into a single-quoted PHP string. */
class ControllerWithSignatureCastSpelling
{
    /** The cast retypes the `\_cast` signature. */
    #[TsCasts(['[key: `${string}\\_cast`]' => 'number'])]
    public function show(): Response
    {
        return Inertia::render('Escaped/Show', [...$this->keys(), 'id' => 1]);
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
