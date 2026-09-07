<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fixture: broadcastWith() keys differ from the public properties, so a port that silently
 * fell back to publicProperties() would emit the wrong shape.
 */
final class PayloadDiffersEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param  list<string>  $items */
    public function __construct(public int $teamId, private array $items) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('teams.'.$this->teamId);
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['team' => $this->teamId, 'kind' => 'message', 'count' => count($this->items)];
    }
}
