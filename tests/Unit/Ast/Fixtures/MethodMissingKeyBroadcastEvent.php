<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A broadcastWith() cast names a key the payload lacks, which a method-level cast adds as a resource's does. */
final class MethodMissingKeyBroadcastEvent implements ShouldBroadcast
{
    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('missing');
    }

    /** @return array<string, mixed> */
    #[TsCasts(['methodMissing' => ['type' => 'Voucher', 'import' => '@/types/voucher']])]
    public function broadcastWith(): array
    {
        return ['id' => 1];
    }
}
