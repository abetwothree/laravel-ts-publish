<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Team;

/**
 * Broadcasts a team through toResource(), a model method that means the same in an event as in a resource: the
 * payload is JSON-encoded, so the key holds the resource's shape.
 */
class TeamRosterSynced implements ShouldBroadcast
{
    public function __construct(public readonly Team $team) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("teams.{$this->team->id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['team' => $this->team->toResource()];
    }
}
