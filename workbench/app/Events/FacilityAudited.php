<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\ExcludedModel;
use Workbench\App\Models\Facility;
use Workbench\App\Packages\Audit\Models\AuditTrail;

/**
 * Carries a model published on demand and a model that is never published.
 */
class FacilityAudited implements ShouldBroadcast
{
    public function __construct(
        public readonly Facility $facility,
        public readonly AuditTrail $trail,
        public readonly ExcludedModel $record,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("facilities.{$this->facility->id}");
    }
}
