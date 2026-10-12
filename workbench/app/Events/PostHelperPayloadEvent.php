<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;

/**
 * Fixture: a broadcastWith() payload built by a helper whose typed parameter its body reads, spread and as a value.
 */
class PostHelperPayloadEvent implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    /** The channel the event broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('posts');
    }

    /**
     * The payload, the helper's row spread and nested.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [...$this->row($this->post), 'row' => $this->row($this->post)];
    }

    /**
     * The post's identity fields.
     *
     * @return array<string, mixed>
     */
    private function row(Post $post): array
    {
        return ['post_id' => $post->id, 'post_title' => $post->title];
    }
}
