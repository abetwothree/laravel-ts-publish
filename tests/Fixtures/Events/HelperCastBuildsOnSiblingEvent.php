<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;

/** A test-only event spreading a helper whose cast builds on the model the outer payload reads. */
final class HelperCastBuildsOnSiblingEvent implements ShouldBroadcast
{
    /** Takes the model its payload reads. */
    public function __construct(public User $user) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('helper-cast-builds-on-sibling');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [...$this->head(), 'user' => $this->user];
    }

    /**
     * The key the helper casts.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['k' => ['type' => 'Paginated<User>', 'import' => '@/types/meta']])]
    private function head(): array
    {
        return ['k' => 'x'];
    }
}
