<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Renders a prop whose key is not an identifier, so the page type must quote it. */
class InertiaKeyEdgesController
{
    public function show(): Response
    {
        return Inertia::render('KeyEdges/Show', ['can-edit' => true, 'ok' => 1]);
    }
}
