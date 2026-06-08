<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Fixture for testing event-name conflict aliasing in the index and echo writers.
 *
 * Paired with Workbench\Crm\Events\UserSynced — both share the short class name
 * 'UserSynced', which triggers alias generation (AppUserSynced / CrmUserSynced)
 * in the broadcast-events.ts index file.
 */
class UserSynced implements ShouldBroadcast
{
    public function __construct(
        public readonly string $userId,
        public readonly string $action,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("users.{$this->userId}");
    }
}
