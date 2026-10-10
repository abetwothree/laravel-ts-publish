<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Broadcasts list literals, which json_encode() writes as JSON arrays.
 */
class HandoverListed implements ShouldBroadcast
{
    public function __construct(public int $handoverId) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stages' => ['sent', 'received'],
            'ids' => [$this->handoverId, 0],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('handovers');
    }
}
