<?php

declare(strict_types=1);

namespace Workbench\Crm\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status as AppStatus;
use Workbench\Crm\Enums\Status as CrmStatus;

class StatusSynced implements ShouldBroadcast
{
    public function __construct(
        public readonly AppStatus $status,
        public readonly CrmStatus $crmStatus,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("statuses.{$this->crmStatus->value}");
    }
}
