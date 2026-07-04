<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Status;

class EnumBroadcastEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Status $status,
        public readonly Color $color,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('enum-events');
    }
}
