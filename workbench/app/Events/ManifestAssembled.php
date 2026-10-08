<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Builds its broadcast payload in a local variable and returns it: a key written on one path only publishes optional.
 */
class ManifestAssembled implements ShouldBroadcast
{
    public function __construct(
        public int $parcelId,
        public string $carrier,
        public bool $express = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = ['parcelId' => $this->parcelId, 'carrier' => $this->carrier];

        if ($this->express) {
            $payload['priority'] = 'express';
        }

        return $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('manifests');
    }
}
