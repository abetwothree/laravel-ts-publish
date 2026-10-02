<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only class whose members are typed by a docblock union of two models that share a name. Each member renders
 * one `User[]` token with both classes behind it.
 */
class ReceiverPairDirectory
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
