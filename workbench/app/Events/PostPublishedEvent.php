<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;

/**
 * Fired when a post is published. Broadcasts the full post and a message.
 */
class PostPublishedEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Post $post,
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("posts.{$this->post->id}");
    }
}
