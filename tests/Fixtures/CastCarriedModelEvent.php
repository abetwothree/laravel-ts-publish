<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only event whose class-level #[TsCasts] retypes the key that reads the app's `User` to a type of the app's
 * own, beside a key that reads a CRM `User`.
 */
#[TsCasts(['reviewer' => ['type' => 'ReviewerCard | null', 'import' => '@js/types/reviews']])]
final class CastCarriedModelEvent implements ShouldBroadcast
{
    /** Takes the two models its payload reads. */
    public function __construct(public User $reviewer, public CrmUser $contact) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-carried-model');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['reviewer' => $this->reviewer, 'contact' => $this->contact];
    }
}
