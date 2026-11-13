<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Inertia actions whose props literal writes a prop, then writes it again through a helper or a declared shape. */
class SpreadFoldPropsController
{
    /** Renders a plain prop a helper then writes again as an optional prop. */
    public function show(): Response
    {
        return Inertia::render('SpreadFold/Show', ['probe' => 5, ...$this->lazyProbe()]);
    }

    /** Renders a plain prop a shape then writes again, its `@return` declaring the prop optional. */
    public function declared(Request $request): Response
    {
        return Inertia::render('SpreadFold/Declared', ['probe' => 5, ...FoldBox::maybeProbe((string) $request->query('pick'))]);
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
