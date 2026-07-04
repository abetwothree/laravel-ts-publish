<?php

declare(strict_types=1);

namespace Workbench\App\Events;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

#[TsCasts([
    'post' => ['type' => 'PostSnapshot', 'import' => '@js/types/snapshots'],
])]
class MixedTypesEvent implements ShouldBroadcast
{
    public function __construct(
        public readonly Post $post,
        public readonly Status $status,
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("mixed.{$this->post->id}");
    }
}
