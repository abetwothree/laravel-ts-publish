<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Events\Concerns\HasBroadcastTimestamps;

class UserNotification implements ShouldBroadcast
{
    use HasBroadcastTimestamps;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PresenceChannel("user.{$this->userId}");
    }
}
