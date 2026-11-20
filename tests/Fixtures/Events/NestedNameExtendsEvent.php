<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;

/** A test-only event whose extends clause, with an import, only builds on the name of the model its payload reads. */
#[TsExtends('Pick<User, "id">', '@/types/user')]
final class NestedNameExtendsEvent implements ShouldBroadcast
{
    /** Takes the model its payload reads. */
    public function __construct(public User $user) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('nested-name-extends');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['user' => $this->user];
    }
}
