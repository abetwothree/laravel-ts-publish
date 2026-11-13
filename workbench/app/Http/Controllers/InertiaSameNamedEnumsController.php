<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\Deal;

/**
 * Renders two same-named enums as cases, as a deal's casts and by their case names, so its route file aliases both.
 */
class InertiaSameNamedEnumsController
{
    public function pair(Deal $deal): Response
    {
        return Inertia::render('SameNamedEnums/Pair', [
            'app' => Status::Draft,
            'crm' => CrmStatus::Lead,
            'app_cast' => $deal->status,
            'crm_cast' => $deal->crm_status,
        ]);
    }

    public function names(Deal $deal): Response
    {
        return Inertia::render('SameNamedEnums/Names', [
            'app_name' => $deal->status->name,
            'crm_name' => $deal->crm_status->name,
        ]);
    }
}
