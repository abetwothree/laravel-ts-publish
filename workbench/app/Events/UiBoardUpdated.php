<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Packages\Ui\Badge;
use Workbench\App\Packages\Ui\Board;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

/**
 * Fixture: payload classes whose override is inherited from a parent or an interface, or stands alone, so each
 * property publishes one override and the file imports them on one line; composed classes, whose `…Type` the file
 * imports from their module; and a value object whose shape keeps each member's override.
 */
final class UiBoardUpdated implements ShouldBroadcast
{
    public function __construct(
        public Panel $panel,
        public Gauge $gauge,
        public Pennies $pennies,
        public Badge $badge,
        public ?Panel $maybe = null,
        public Chart $chart = new Chart,
        public Dial $dial = new Dial,
        public ?Chart $maybeChart = null,
        /** @var list<Chart> */
        public array $charts = [],
        public Board $board = new Board,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('ui-board');
    }
}
