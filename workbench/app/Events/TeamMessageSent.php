<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TeamMessageSent implements ShouldBroadcast
{
    public function __construct(
        public int $teamId,
        public string $content,
        private string $senderToken,
    ) {}

    /**
     * Override the broadcast payload (excludes private $senderToken).
     *
     * @return array{teamId: int, content: string}
     */
    public function broadcastWith(): array
    {
        return [
            'teamId' => $this->teamId,
            'content' => $this->content,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("teams.{$this->teamId}");
    }
}
