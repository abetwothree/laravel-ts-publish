<?php

declare(strict_types=1);

namespace Workbench\Crm\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User as AppUser;
use Workbench\Crm\Models\User as CrmUser;

class UserSynced implements ShouldBroadcast
{
    public function __construct(
        public readonly AppUser $user,
        public readonly CrmUser $crmUser,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("users.{$this->crmUser->id}");
    }
}
