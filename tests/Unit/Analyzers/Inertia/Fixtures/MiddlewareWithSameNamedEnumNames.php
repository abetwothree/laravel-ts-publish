<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** Shared props reading two same-named enums' case names beside a third enum's. */
final class MiddlewareWithSameNamedEnumNames extends Middleware
{
    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    private Priority $priority = Priority::Low;

    public function share(Request $request): array
    {
        return [
            'app_name' => $this->status->name,
            'crm_name' => $this->crmStatus->name,
            'priority_name' => $this->priority->name,
        ];
    }
}
