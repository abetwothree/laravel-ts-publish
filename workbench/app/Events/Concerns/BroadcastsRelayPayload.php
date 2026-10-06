<?php

declare(strict_types=1);

namespace Workbench\App\Events\Concerns;

/**
 * Supplies an event's broadcastWith() from its own file.
 */
trait BroadcastsRelayPayload
{
    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
            'channel' => $this->channel,
        ];
    }
}
