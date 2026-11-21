<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * One row a guard resource maps, holding the app and the CRM `Status`.
 */
final class GuardRow
{
    public function __construct(
        public Status $status,
        public CrmStatus $crmStatus,
    ) {}
}
