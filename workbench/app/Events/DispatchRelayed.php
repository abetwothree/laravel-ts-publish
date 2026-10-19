<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Events\Concerns\BroadcastsRelayPayload;

/**
 * Takes its broadcastWith() from a trait declared in another file, which Laravel calls as the event's own method.
 */
class DispatchRelayed implements ShouldBroadcast
{
    use BroadcastsRelayPayload;

    public function __construct(
        public int $dispatchId,
        public string $channel,
        private string $relayToken,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("dispatches.{$this->dispatchId}");
    }
}
