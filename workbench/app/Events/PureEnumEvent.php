<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Visibility;

class PureEnumEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Role $role,
        public readonly Visibility $visibility,
        public readonly string $action,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('pure-enum-events');
    }
}
