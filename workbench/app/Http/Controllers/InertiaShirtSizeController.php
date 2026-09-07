<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Image;

/** Renders a page prop typed by an enum whose exported TypeScript name differs from its class basename. */
class InertiaShirtSizeController
{
    public function show(Image $image): Response
    {
        return Inertia::render('Shirts/Show', ['size' => $image->shirt_size]);
    }
}
