<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** broadcastWith()'s own `@return` types an untyped value as string literals that spell class names. */
final class LiteralKindPostEvent implements ShouldBroadcast
{
    /** The channel. */
    public function broadcastOn(): Channel
    {
        return new Channel('posts');
    }

    /**
     * The payload, over a value the body cannot type.
     *
     * @return array{kind: 'Comment'|'Post'}
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->opaque()];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return json_decode('{}');
    }
}
