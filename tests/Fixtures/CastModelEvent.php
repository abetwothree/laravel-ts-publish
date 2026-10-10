<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only event whose class-level #[TsCasts] imports a type named like the model its key reads,
 * beside a key that reads a CRM `User`.
 */
#[TsCasts(['user' => ['type' => 'User | null', 'import' => '@js/types/user']])]
final class CastModelEvent implements ShouldBroadcast
{
    /** Takes the two models its payload reads. */
    public function __construct(public User $user, public CrmUser $crm) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-model');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['user' => $this->user, 'crm' => $this->crm];
    }
}
