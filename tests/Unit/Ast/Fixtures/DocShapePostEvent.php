<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;

/** broadcastWith()'s own `@return` names `User` without importing it, so PHP resolves the name to no class. */
final class DocShapePostEvent implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    /** The channel. */
    public function broadcastOn(): Channel
    {
        return new Channel('posts');
    }

    /**
     * The payload, over a value the body cannot type.
     *
     * @return array{user: User}
     */
    public function broadcastWith(): array
    {
        return ['user' => $this->opaque()];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return json_decode('{}');
    }
}
