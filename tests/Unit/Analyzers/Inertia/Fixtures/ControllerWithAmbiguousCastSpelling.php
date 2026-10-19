<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;

/** A page whose cast key spells both of the props' backslash signatures' names. */
class ControllerWithAmbiguousCastSpelling
{
    /** The cast retypes neither signature. */
    #[TsCasts(['[key: `${string}\\\\r`]' => 'number'])]
    public function show(): Response
    {
        return Inertia::render('Ambiguous/Show', [...$this->keys(), 'id' => 1]);
    }

    /** Keys whose literal text holds one or two backslashes before an `r`. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\\r"] = 1;
            $data["{$name}\\\\r"] = 1;
        }

        return $data;
    }
}
