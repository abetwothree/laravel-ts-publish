<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Casts its `\_x` signature by the exact name on the class and by the single-backslash paste on casts(). */
#[TsCasts(['[key: `${string}\\\\_x`]' => 'string'])]
final class SpellingsAcrossLocationsBroadcastEvent implements ShouldBroadcast
{
    /**
     * The casts() location, which outranks the class.
     *
     * @return array<string, string>
     */
    #[TsCasts(['[key: `${string}\\_x`]' => 'number'])]
    public function casts(): array
    {
        return [];
    }

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('spellings');
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
            $data["{$name}\\_x"] = 'x';
        }

        return $data;
    }
}
