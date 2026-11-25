<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\EnumWraps;

use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Address;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * The enums, the address, flags and rows the enum-wrap probe resources read (GH-160).
 */
final class EnumWrapBox
{
    public function __construct(
        public Status $status = Status::Draft,
        public CrmStatus $crmStatus = CrmStatus::Lead,
        public Role $role = Role::Guest,
        public Visibility $visibility = Visibility::Public,
        public Priority $priority = Priority::Low,
        public MediaType $kind = MediaType::Image,
        public ?MediaType $maybeKind = null,
        public ?Address $address = null,
        public bool $c = false,
        public bool $d = false,
        public bool $e = false,
        public string $pick = 'x',
        /** @var list<EnumWrapRow> */
        public array $rows = [],
    ) {}
}
