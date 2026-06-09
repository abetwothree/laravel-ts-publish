<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class MultiModelEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Post $post,
        public readonly User $user,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("multi.{$this->post->id}");
    }
}
