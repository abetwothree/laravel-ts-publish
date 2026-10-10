<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;

/**
 * A test-only event whose class-level #[TsCasts] displaces a model, an enum and a resource, and spells each name,
 * without an import, on a key with no class behind it.
 */
#[TsCasts([
    'owner' => 'string',
    'state' => 'string',
    'team' => 'string',
    'label' => 'User | null',
    'tag' => 'StatusType',
    'ref' => 'TeamResource | null',
])]
final class CastCarriedSpelledEvent implements ShouldBroadcast
{
    /** Takes the models its payload reads. */
    public function __construct(public User $user, public Warehouse $warehouse, public Team $team) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-carried-spelled');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'owner' => $this->user,
            'state' => $this->warehouse->status,
            'team' => $this->team->toResource(),
            'label' => 'x',
            'tag' => 'x',
            'ref' => 'x',
        ];
    }
}
