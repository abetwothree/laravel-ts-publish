<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** An Inertia action whose closure props' bodies type nothing, typed by the `@return` docblock written before each. */
class DocumentedClosurePropsController
{
    /** Renders a documented closure prop as an array value, and one passed to an optional prop. */
    public function show(): Response
    {
        return Inertia::render('DocumentedClosureProps/Show', [
            'label' => /** @return string */ fn () => json_decode('"x"'),
            'count' => Inertia::optional(/** @return int */ fn () => json_decode('1')),
        ]);
    }
}
