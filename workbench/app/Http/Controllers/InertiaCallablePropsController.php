<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Facility;

class InertiaCallablePropsController
{
    /**
     * Passes first-class callables as props, which Inertia calls before it sends them, like a closure prop.
     */
    public function show(Facility $facility): Response
    {
        return Inertia::render('Facility/Callables', [
            'stamp' => date_default_timezone_get(...),
            'label' => $this->label(...),
            'viewer' => auth()->user(...),
            'closure_label' => fn () => $this->label(),
        ]);
    }

    /** The page's label. */
    public function label(): string
    {
        return 'facility';
    }
}
