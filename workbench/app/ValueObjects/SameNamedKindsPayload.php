<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Fixture: two same-named backed enums whose `->value` and `->name` a payload reads, so each `…Kind` alias takes the
 * prefix its `…Type` alias is given; `unions()` reads both enums in one ternary, and `inner()` both names in one
 * nested array.
 */
final class SameNamedKindsPayload
{
    public Status $status = Status::Draft;

    public CrmStatus $crmStatus = CrmStatus::Lead;

    public bool $first = true;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'status_name' => $this->status->name,
            'crm_status' => $this->crmStatus->value,
            'crm_status_name' => $this->crmStatus->name,
        ];
    }

    /** @return array<string, mixed> */
    public function unions(): array
    {
        return [
            'value_or_name' => $this->first ? $this->status->value : $this->crmStatus->name,
            'name_or_name' => $this->first ? $this->status->name : $this->crmStatus->name,
        ];
    }

    /** @return array<string, mixed> */
    public function inner(): array
    {
        return [
            'inner' => ['a' => $this->status->name, 'b' => $this->crmStatus->name],
        ];
    }
}
