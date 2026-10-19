<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A payload that spreads a helper writing a backslash signature. */
final class SpreadEscapedKeyBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('spread');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['id' => 1, ...$this->units()];
    }

    /**
     * The keys whose literal text holds a backslash.
     *
     * @return array<string, string>
     */
    private function units(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\unit"] = 'Unit';
        }

        return $data;
    }
}
