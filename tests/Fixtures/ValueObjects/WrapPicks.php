<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * The enums an API resource wraps or reads: the app and CRM statuses, a role and a media kind, beside two flags that
 * pick a branch and rows only a `@return` types.
 */
final readonly class WrapPicks
{
    public function __construct(
        public Status $status,
        public CrmStatus $crmStatus,
        public Role $role,
        public MediaType $kind,
        public bool $c = false,
        public bool $d = false,
        /** @var list<ReviewRow> */
        public array $rows = [],
    ) {}
}
