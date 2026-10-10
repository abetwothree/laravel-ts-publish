<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Its one cast key, with an import, spells both payload signatures' names, and a second names no payload key. */
#[TsCasts([
    '[key: `${string}\\\\r`]' => ['type' => 'Money', 'import' => '@/types/money'],
    'missing' => ['type' => 'Coupon', 'import' => '@/types/coupon'],
])]
final class AmbiguousSpellingBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('ambiguous');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = ['id' => 1];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\\r"] = 1;
            $data["{$name}\\\\r"] = 1;
        }

        return $data;
    }
}
