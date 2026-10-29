<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** A test-only controller whose pages read two same-named enums' case names, in one page and across two pages. */
class SameNamedEnumNamesController
{
    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    private Priority $priority = Priority::Low;

    public function both(): Response
    {
        return Inertia::render('Kinds/Both', [
            'app_name' => $this->status->name,
            'crm_name' => $this->crmStatus->name,
            'priority_name' => $this->priority->name,
        ]);
    }

    public function app(): Response
    {
        return Inertia::render('Kinds/App', ['name' => $this->status->name]);
    }

    public function crm(): Response
    {
        return Inertia::render('Kinds/Crm', ['name' => $this->crmStatus->name]);
    }
}
