<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A docblock-filled `_tag` signature on a payload that extends an interface whose keys the package cannot see. */
#[TsExtends('HasPriceTag')]
final class ExtendedTagSignatureBroadcastEvent implements ShouldBroadcast
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
        return [...$this->docTags()];
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
