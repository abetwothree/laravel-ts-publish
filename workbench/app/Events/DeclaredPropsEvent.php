<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fixture: class-body declared properties, a @var list, and a nullable promoted param.
 */
final class DeclaredPropsEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $label;

    /** @var list<string> */
    public array $tags = [];

    public function __construct(public int $id, public ?string $note = null)
    {
        $this->label = 'event-'.$id;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('declared');
    }
}
