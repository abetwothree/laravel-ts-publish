<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Http\Resources\TagResource;

/** An event whose payload wraps null in a resource, which throws at runtime, so it keeps the resource's type. */
final class NullWrapBroadcastEvent implements ShouldBroadcast
{
    /** The channel. */
    public function broadcastOn(): Channel
    {
        return new Channel('tags');
    }

    /**
     * The payload: a resource built around null.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'tag' => new TagResource(null),
        ];
    }
}
