<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;

/** A test-only event whose class-level #[TsCasts] builds on the model another key reads, beside a name only it imports. */
#[TsCasts(['k' => ['type' => '{ owner: User; meta: Meta }', 'import' => '@/types/meta']])]
final class CastBuildsOnSiblingModelEvent implements ShouldBroadcast
{
    /** Takes the model its payload reads. */
    public function __construct(public User $user) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-builds-on-sibling-model');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['k' => 'x', 'user' => $this->user];
    }
}
