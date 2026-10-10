<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Fixture: a `broadcastWith()` cast retypes the app's `User` to a type of the app's own, beside a CRM `User`, so the
 * cast is published as written and the app's `User` is not imported.
 */
final class ReviewerCastEvent implements ShouldBroadcast
{
    public function __construct(public User $reviewer, public CrmUser $contact) {}

    public function broadcastOn(): Channel
    {
        return new Channel('reviews');
    }

    /**
     * @return array<string, mixed>
     */
    #[TsCasts(['reviewer' => ['type' => 'ReviewerCard | null', 'import' => '@js/types/reviews']])]
    public function broadcastWith(): array
    {
        return ['reviewer' => $this->reviewer, 'contact' => $this->contact];
    }
}
