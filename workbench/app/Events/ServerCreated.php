<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[TsExtends('BroadcastableEvent', '@/types/broadcast')]
class ServerCreated implements ShouldBroadcast
{
    public function __construct(
        public int $serverId,
        public string $serverName,
    ) {}

    /**
     * Override the broadcast event name (custom Echo listen key).
     */
    public function broadcastAs(): string
    {
        return 'server.created';
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('servers');
    }
}
