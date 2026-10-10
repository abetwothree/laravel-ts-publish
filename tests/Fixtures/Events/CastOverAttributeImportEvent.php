<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Stockroom;

/**
 * A test-only event whose class-level #[TsCasts] imports a type named like the `#[TsType]` import of the attribute its
 * key reads.
 */
#[TsCasts(['menu' => ['type' => 'MenuSettingsType | null', 'import' => '@js/types/menu']])]
final class CastOverAttributeImportEvent implements ShouldBroadcast
{
    /** Takes the stockroom its payload reads. */
    public function __construct(public Stockroom $stockroom) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('cast-over-attribute-import');
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
