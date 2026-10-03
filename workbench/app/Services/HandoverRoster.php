<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** Lists the people a handover involves, as arrays of either of two models that share a name. */
class HandoverRoster
{
    /** @var User[]|CrmUser[] */
    public array $members = [];

    /** @return list<User>|list<CrmUser> */
    public function reviewers(): array
    {
        return [];
    }
}
