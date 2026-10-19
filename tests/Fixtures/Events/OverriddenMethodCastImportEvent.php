<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event whose class-level #[TsCasts] imports a name its `broadcastWith()` cast imports from elsewhere. */
#[TsCasts(['card' => ['type' => 'ReviewerCard | null', 'import' => '@js/types/reviews']])]
final class OverriddenMethodCastImportEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('overridden-method-cast-import');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['card' => ['type' => 'ReviewerCard | null', 'import' => '@js/types/cards']])]
    public function broadcastWith(): array
    {
        return ['card' => 1];
    }
}
