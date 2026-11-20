<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

/**
 * The value every generated guard resource reads: one typed property per class an arm holds, and the flags and pick
 * its branches test.
 */
final class GuardBox
{
    public function __construct(
        public Status $status,
        public CrmStatus $crmStatus,
        public Role $role,
        public User $user,
        public CrmUser $crmUser,
        public Address $address,
        /** @var list<GuardRow> */
        public array $rows = [],
        public bool $a = false,
        public bool $b = false,
        public bool $c = false,
        public string $pick = 'a',
    ) {}
}
