<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Casts its payload's numeric key `42`, which PHP stores as an int. */
#[TsCasts(['name' => "'a' | 'b'", '42' => 'boolean'])]
final class NumericCastKeyBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('numeric');
    }

    /**
     * The payload.
     *
     * @return array<array-key, mixed>
     */
    public function broadcastWith(): array
    {
        return ['name' => 'a', 42 => 1];
    }
}
