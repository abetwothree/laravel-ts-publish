<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;

/**
 * Broadcasts a post's status and its nullable visibility wrapped in enum resources (GH-109), which send each enum
 * resource's object, and `[]` for a null visibility.
 */
class EnumWrapsBroadcast implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => EnumResource::make($this->post->status),
            'visibility' => EnumResource::make($this->post->visibility),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('enum-wraps');
    }
}
