<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;

class UserRegisteredEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly User $user,
        public readonly int $userId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("users.{$this->userId}");
    }
}
