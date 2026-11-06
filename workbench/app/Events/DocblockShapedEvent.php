<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * broadcastWith() whose body types nothing, so only its own @return shape can.
 */
final class DocblockShapedEvent implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    public function broadcastOn(): Channel
    {
        return new Channel('posts');
    }

    /** @return array{published_at: string|null} */
    public function broadcastWith(): array
    {
        return ['published_at' => $this->opaque()];
    }

    /** Deliberately untyped. */
    private function opaque()
    {
        return Opaque::value($this->post->published_at);
    }
}
