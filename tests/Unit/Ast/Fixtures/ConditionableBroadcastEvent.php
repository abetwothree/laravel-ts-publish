<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\User;

/** An event whose `$this->when()` is Conditionable's, which returns the callback's value or the event itself. */
final class ConditionableBroadcastEvent implements ShouldBroadcast
{
    use Conditionable;

    /** Carries the user the payload reads. */
    public function __construct(public User $user) {}

    /** The channel. */
    public function broadcastOn(): Channel
    {
        return new Channel('users');
    }

    /**
     * The payload: a Conditionable call, and a model method that builds a resource.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'label' => $this->when($this->user->exists, fn () => 'active'),
            'user' => $this->user->toResource(),
        ];
    }
}
