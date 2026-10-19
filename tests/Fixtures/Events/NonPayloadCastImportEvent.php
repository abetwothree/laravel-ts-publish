<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Stockroom;

/**
 * A test-only event whose class-level #[TsCasts] imports, for a key no payload property names, the name of the
 * `#[TsType]` import of the attribute its payload reads.
 */
#[TsCasts(['missing' => ['type' => 'MenuSettingsType', 'import' => '@js/types/menu']])]
final class NonPayloadCastImportEvent implements ShouldBroadcast
{
    /** Takes the stockroom its payload reads. */
    public function __construct(public Stockroom $stockroom) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('non-payload-cast-import');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['menu' => $this->stockroom->menu_config];
    }
}
