<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** Its cast key is pasted from the published name of a backslash signature into a single-quoted PHP string. */
#[TsCasts(['[key: `${string}\\_cast`]' => 'number'])]
final class SignatureCastSpellingBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('escaped');
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
            $data["{$name}\\_cast"] = 'x';
        }

        return $data;
    }
}
