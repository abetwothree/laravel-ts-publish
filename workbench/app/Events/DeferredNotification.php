<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Fixture: a typed public property left uninitialized, one defaulted, one promoted — proves
 * json_encode() omits an uninitialized typed property, so its TypeScript key must be optional.
 *
 * Does not use HasBroadcastTimestamps: that trait already declares a `string $occurredAt`, which
 * fatals PHP's trait composition against this fixture's own `Carbon $occurredAt`.
 */
class DeferredNotification implements ShouldBroadcast
{
    /** Typed and never assigned: json_encode() omits it, so the TypeScript key must be optional. */
    public Carbon $occurredAt;

    public ?string $note = null;

    public function __construct(
        public int $userId,
        public string $title,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PresenceChannel("user.{$this->userId}");
    }
}
