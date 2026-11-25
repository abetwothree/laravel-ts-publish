<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\EnumWraps;

use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * One row the enum-wrap probes map, holding the app and the CRM `Status` (GH-160).
 */
final class EnumWrapRow
{
    public function __construct(
        public Status $status = Status::Draft,
        public CrmStatus $crmStatus = CrmStatus::Lead,
        public string $name = '',
    ) {}
}
