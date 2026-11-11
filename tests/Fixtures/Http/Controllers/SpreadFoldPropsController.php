<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Inertia actions whose props literal writes a prop, then writes it again as a prop Inertia may leave out. */
class SpreadFoldPropsController
{
    /** Renders a plain prop a helper then writes again as an optional prop. */
    public function show(): Response
    {
        return Inertia::render('SpreadFold/Show', ['probe' => 5, ...$this->lazyProbe()]);
    }

    /**
     * The probe prop as an optional prop, sent only on a partial reload that asks for it.
     *
     * @return array<string, mixed>
     */
    private function lazyProbe(): array
    {
        return ['probe' => Inertia::optional(fn (): string => 'x')];
    }
}
