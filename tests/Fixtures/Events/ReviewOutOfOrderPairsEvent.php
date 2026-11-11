<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event sending rows keyed by ints out of order, which json_encode() sends as objects (GH-154). */
final class ReviewOutOfOrderPairsEvent implements ShouldBroadcast
{
    /** @var list<int> */
    public array $rows = [1, 2];

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('out-of-order-pairs');
    }

    /**
     * The payload.
     *
     * @return array{pairs: list<array{0: int, 1: string}>, pair: array{0: int, 1: string}}
     */
    public function broadcastWith(): array
    {
        return [
            'pairs' => array_map(fn (int $row) => [1 => cache('x'), 0 => cache('y')], $this->rows),
            'pair' => [1 => cache('x'), 0 => cache('y')],
        ];
    }
}
