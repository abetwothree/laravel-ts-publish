<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Facility;
use Workbench\App\Packages\Audit\Models\AuditTrail;

class InertiaFacilityController
{
    /**
     * Passes a model published on demand and a model that is never published.
     */
    public function show(Facility $facility): Response
    {
        return Inertia::render('Facility/Show', [
            'facility' => $facility,
            'trail' => AuditTrail::query()->first(),
            'record' => ExcludedModel::query()->first(),
        ]);
    }
}
