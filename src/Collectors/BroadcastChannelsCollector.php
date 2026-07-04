<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Collection;

class BroadcastChannelsCollector
{
    public function __construct(protected BroadcastManager $broadcastManager) {}

    /**
     * Collect all registered broadcast channel names.
     *
     * Reads directly from BroadcastManager, which holds every channel
     * registered via Broadcast::channel() in the application's channels.php.
     *
     * @return Collection<int, string>
     */
    public function collect(): Collection
    {
        /** @var Collection<int, string> */
        return $this->broadcastManager->getChannels()->keys();
    }
}
