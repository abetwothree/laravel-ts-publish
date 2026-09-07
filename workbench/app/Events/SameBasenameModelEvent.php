<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Fixture: a public property whose @var unions two same-basename models from different namespaces,
 * with no broadcastWith() — so the payload comes from analyzePublicProperties() and both tokens
 * must be aliased apart rather than collapsing to a repeated `User`.
 */
final class SameBasenameModelEvent implements ShouldBroadcast
{
    /** @var User|CrmUser */
    public $actor;

    public function broadcastOn(): Channel
    {
        return new Channel('same-basename');
    }
}
