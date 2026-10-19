<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Broadcasts bare enum cases, two of them from enums that share a basename.
 */
class PostStatusBroadcast implements ShouldBroadcast
{
    public function __construct(public readonly Post $post) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => Status::Published,
            'crm_status' => CrmStatus::Active,
            'kept' => $this->post->status ?? Status::Draft,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('post-status');
    }
}
