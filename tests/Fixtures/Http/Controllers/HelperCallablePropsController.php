<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** An Inertia action whose prop a helper method builds around a first-class callable. */
class HelperCallablePropsController
{
    /** Renders the helper's array as one prop. */
    public function show(): Response
    {
        return Inertia::render('HelperCallableProps/Show', ['meta' => $this->meta()]);
    }

    /**
     * The prop's array, holding a callable Inertia calls.
     *
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        return ['stamp' => now(...)];
    }
}
