<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Cog;
use Inertia\Inertia;
use Inertia\Response;

/** An Inertia action rendering a guessed collection whose #[UseResource] names a resource collection. */
class DeclinedGuessController
{
    /** Renders the models' guessed resource collection, which the page-prop handler declines. */
    public function index(): Response
    {
        return Inertia::render('DeclinedGuess/Index', ['cogs' => Cog::all()->toResourceCollection()]);
    }
}
