<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Warehouse;

/**
 * A test-only event whose inline payload spreads a helper whose #[TsCasts] spells the enum its key reads, before a key
 * that reads another enum of that type name.
 */
final class InlineSpreadCastEnumEvent implements ShouldBroadcast
{
    /** Takes the warehouse its payload reads. */
    public function __construct(public Warehouse $warehouse) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('inline-spread-cast-enum');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['nested' => [...$this->statuses(), 'crm' => $this->warehouse->current_crm_status]];
    }

    /**
     * The statuses the payload spreads.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['status' => 'StatusType | null'])]
    protected function statuses(): array
    {
        return ['status' => $this->warehouse->status];
    }
}
