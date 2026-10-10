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
 * Fixture: broadcastWith() spreads keys built from literal text around a variable, so the payload holds an index
 * signature, which must print bare rather than as a quoted property.
 */
final class TaggedPayloadEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $id) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('tagged.'.$this->id);
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->id, ...$this->tags()];
    }

    /** The body types these values. */
    private function tags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
    }
}
