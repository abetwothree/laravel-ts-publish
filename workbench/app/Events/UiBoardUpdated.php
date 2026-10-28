<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Packages\Ui\Badge;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

/**
 * Fixture: payload classes whose override is inherited from a parent or an interface, or stands alone, so each
 * property publishes one override and the file imports them on one line.
 */
final class UiBoardUpdated implements ShouldBroadcast
{
    public function __construct(
        public Panel $panel,
        public Gauge $gauge,
        public Pennies $pennies,
        public Badge $badge,
        public ?Panel $maybe = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('ui-board');
    }
}
