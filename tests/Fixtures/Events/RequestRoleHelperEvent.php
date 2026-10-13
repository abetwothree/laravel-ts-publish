<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Http\Request;

/** A test-only event whose payload wraps the requesting user's role through a helper that takes the request. */
final class RequestRoleHelperEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('request-role');
    }

    /**
     * The payload, the requesting user's role read by the helper.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['role' => $this->roleOf(request())];
    }

    /** The requesting user's role as an enum resource. */
    private function roleOf(Request $request): EnumResource
    {
        return EnumResource::make($request->user()->role);
    }
}
