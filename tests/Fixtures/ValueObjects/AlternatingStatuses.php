<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** A value object whose public properties alternate between two same-named enums: the app's, the CRM's, the app's. */
final class AlternatingStatuses
{
    public Status $first = Status::Draft;

    public CrmStatus $crm = CrmStatus::Lead;

    public ?Status $last = null;
}
