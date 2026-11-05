<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

/** A test-only event building a resource around its own Conditionable `$this->when()`, which never leaves a key out. */
final class ConditionableWrapEvent implements ShouldBroadcast
{
    use Conditionable;

    /** Carries the user the payload wraps. */
    public function __construct(public User $user) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-wrap');
    }

    /**
     * The payload: the resource built by make(), by `new` and as a collection.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'made' => UserResource::make($this->when($this->user->exists, fn () => $this->user)),
            'built' => new UserResource($this->when($this->user->exists, fn () => $this->user)),
            'listed' => UserResource::collection($this->when($this->user->exists, fn () => [$this->user])),
        ];
    }
}
