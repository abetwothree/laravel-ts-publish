<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[TsCasts([
    'trackingNumber' => '`${string}-${string}-${string}`',
    'metadata' => 'Record<string, unknown>',
])]
class OrderShipped implements ShouldBroadcast
{
    public function __construct(
        public int $orderId,
        public string $trackingNumber,
        public string $carrier,
        public ?array $metadata = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel("orders.{$this->orderId}");
    }
}
