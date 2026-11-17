<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** A test-only event sending a case value beside its enum's alias, an empty list beside a list, and a wrap (GH-166). */
final class RedundantArmsEvent implements ShouldBroadcast
{
    public function __construct(private readonly Post $post, private readonly bool $c = false) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('redundant-arms');
    }

    /**
     * The payload, each key a ternary whose arms the flag picks.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->c ? Status::Draft->value : $this->post->status->value,
            'tags' => $this->c ? [] : [$this->post->priority],
            'kept' => $this->c ? EnumResource::make($this->post->status) : Status::Draft->value,
        ];
    }
}
