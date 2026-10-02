<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A second test-only class whose members hold ReceiverPairDirectory's pair, so a receiver can be either. */
class ReceiverPairRegistry
{
    /** @var User[]|CrmUser[] */
    public array $owners = [];

    /**
     * The owners, as a list of either model.
     *
     * @return list<User>|list<CrmUser>
     */
    public function ownerList(): array
    {
        return [];
    }
}
