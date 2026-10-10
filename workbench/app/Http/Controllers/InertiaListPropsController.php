<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** Renders list literals as page props. */
class InertiaListPropsController
{
    public function show(): Response
    {
        $lanes = [1, 2];

        return Inertia::render('ListProps/Show', [
            'stages' => ['sent', 'received'],
            'lanes' => $lanes,
        ]);
    }
}
