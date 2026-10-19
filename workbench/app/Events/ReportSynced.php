<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Marketing\Report\Report as MarketingReport;
use Workbench\App\Models\Sales\Report\Report as SalesReport;

/**
 * Fixture: broadcasts both Report models, which share a basename AND parent namespace
 * segment ('Report') — reproduces a same-basename alias collision via a broadcast event.
 */
class ReportSynced implements ShouldBroadcast
{
    public function __construct(
        public readonly SalesReport $salesReport,
        public readonly MarketingReport $marketingReport,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('reports');
    }
}
