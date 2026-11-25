<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status;

/** A test-only event sending an enum resource under a key of an inline array (GH-109). */
final class NestedEnumWrapEvent implements ShouldBroadcast
{
    public function __construct(public Status $status = Status::Draft) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('nested-enum-wrap');
    }

    /**
     * The payload, a wrap under `probe.w`.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['probe' => ['w' => EnumResource::make($this->status)]];
    }
}
