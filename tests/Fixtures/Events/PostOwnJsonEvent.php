<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Events\PostPublishedEvent;
use Workbench\App\Models\Post;
use Workbench\App\Services\HandoverRoster;

/** A test-only event holding a post, whose payload sends another event around it and two same-named models' roster. */
final class PostOwnJsonEvent implements ShouldBroadcast
{
    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('post-own-json');
    }

    /**
     * The payload: objects json_encode() writes as their public properties.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'copy' => new PostPublishedEvent($this->post, 'copied'),
            'roster' => new HandoverRoster,
        ];
    }
}
