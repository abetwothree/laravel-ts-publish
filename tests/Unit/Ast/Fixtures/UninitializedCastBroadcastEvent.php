<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Two uninitialized properties: one cast says nothing about `optional`, the other clears it. */
#[TsCasts([
    'note' => "'a' | 'b'",
    'label' => ['type' => "'c' | 'd'", 'optional' => false],
])]
final class UninitializedCastBroadcastEvent implements ShouldBroadcast
{
    public int $id = 1;

    public string $note;

    public string $label;

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('uninitialized');
    }
}
