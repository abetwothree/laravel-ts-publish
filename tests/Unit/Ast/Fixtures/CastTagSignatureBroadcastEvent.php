<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** The event's `#[TsCasts]` retypes `main_tag`, a key the docblock-filled `_tag` signature covers. */
#[TsCasts(['main_tag' => 'number'])]
final class CastTagSignatureBroadcastEvent implements ShouldBroadcast
{
    public mixed $source = null;

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('tags');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [...$this->docTags(), 'main_tag' => 'x'];
    }

    /**
     * `_tag` keys only the docblock types.
     *
     * @return array<string, string>
     */
    public function docTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->source;
    }
}
