<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;

class InertiaTsCastsController
{
    /**
     * Demonstrates TsCasts overrides on an Inertia route action.
     *
     * The `count` prop is auto-detected as `number` by Surveyor; TsCasts
     * overrides it to `string` to verify the override mechanism works.
     * The `meta` prop is not in the Surveyor data, so TsCasts adds it
     * with an import from a custom package.
     */
    #[TsCasts([
        'count' => 'string',
        'meta' => ['type' => 'PageMeta', 'import' => '@workbench/types'],
    ])]
    public function index(): Response
    {
        return Inertia::render('TsCasts/Index', [
            'count' => 42,
        ]);
    }
}
