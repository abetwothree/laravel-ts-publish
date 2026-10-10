<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Sends the int key `42` at the top level of its payload, `7` inside a nested array, and a collection keyed by
 * numbers.
 */
final class NumericPayloadKeyBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('numeric-payload');
    }

    /**
     * The payload.
     *
     * @return array<array-key, mixed>
     */
    public function broadcastWith(): array
    {
        return ['id' => 1, 42 => 'a', 'codes' => [7 => 'b'], 'statuses' => collect([200 => 'OK', 404 => 'Not Found'])];
    }
}
