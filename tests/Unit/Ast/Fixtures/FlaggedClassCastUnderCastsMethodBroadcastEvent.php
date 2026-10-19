<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** The class marks `note` optional; casts() retypes it and says nothing about `optional`. */
#[TsCasts(['note' => ['type' => "'a' | 'b'", 'optional' => true]])]
final class FlaggedClassCastUnderCastsMethodBroadcastEvent implements ShouldBroadcast
{
    public string $note = 'x';

    /**
     * The casts() location's #[TsCasts].
     *
     * @return array<string, string>
     */
    #[TsCasts(['note' => "'c' | 'd'"])]
    public function casts(): array
    {
        return [];
    }

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('flagged');
    }
}
