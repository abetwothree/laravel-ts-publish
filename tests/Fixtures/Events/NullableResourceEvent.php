<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;

/**
 * A broadcast event whose nullable `$resource` member is no API resource's proxy, read through `??` in its payload.
 */
final class NullableResourceEvent implements ShouldBroadcast
{
    public ?Post $resource = null;

    /** @var array<string, int> */
    public array $scores = ['x' => 1];

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('nullable-resource');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'scores' => $this->resource->comments ?? $this->scores,
            'literal' => $this->resource->comments ?? ['a' => 1],
        ];
    }
}
