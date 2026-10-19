<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A payload `_tag` signature its #[TsCasts] marks optional, which a signature cannot carry. */
#[TsCasts(['[key: `${string}_tag`]' => ['type' => 'string', 'optional' => true]])]
final class OptionalSignatureCastBroadcastEvent implements ShouldBroadcast
{
    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('tags')];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $data = ['id' => 1];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
    }
}
