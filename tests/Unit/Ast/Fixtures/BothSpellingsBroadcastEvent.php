<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Casts two signatures under both spellings; each single-backslash one carries a flag or import of its own. */
#[TsCasts([
    '[key: `${string}\\\\_e`]' => 'string',
    '[key: `${string}\\_e`]' => ['type' => 'number', 'optional' => true],
    '[key: `${string}\\\\_f`]' => 'Money',
    '[key: `${string}\\_f`]' => ['type' => 'Money', 'import' => '@/types/money'],
])]
final class BothSpellingsBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('both');
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

    /** Keys whose literal text holds a backslash. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_e"] = 1;
            $data["{$name}\\_f"] = 1;
        }

        return $data;
    }
}
