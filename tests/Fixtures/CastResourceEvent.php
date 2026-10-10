<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Team;

/** A test-only event whose class-level #[TsCasts] retypes a key that holds a resource, without spelling it. */
#[TsCasts(['team' => '{ id: number }'])]
final class CastResourceEvent implements ShouldBroadcast
{
    /** Takes the team its payload reads. */
    public function __construct(public Team $team) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-resource');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['team' => $this->team->toResource()];
    }
}
