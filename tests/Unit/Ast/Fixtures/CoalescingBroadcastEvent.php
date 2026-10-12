<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;

/** An event whose payload falls back with `??`, which no MissingValue reaches. */
final class CoalescingBroadcastEvent implements ShouldBroadcast
{
    /** Carries the user the payload reads. */
    public function __construct(public User $user) {}

    /** The channel. */
    public function broadcastOn(): Channel
    {
        return new Channel('users');
    }

    /**
     * The payload: a fallback beside a plain value.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['x' => $this->user->name ?? 'none'];
    }
}
