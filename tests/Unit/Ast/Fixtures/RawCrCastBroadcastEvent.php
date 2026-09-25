<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Its cast key, a double-quoted PHP string, holds a raw CR where the CR LF signature's published name writes `\r`. */
#[TsCasts(["[key: `\${string}\r\n`]" => 'string'])]
final class RawCrCastBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('raw-cr');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [...$this->keys(), 'id' => 1];
    }

    /** Keys whose literal text ends in CR LF. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\r\n"] = 1;
        }

        return $data;
    }
}
