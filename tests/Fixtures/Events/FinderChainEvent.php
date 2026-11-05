<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A test-only event whose payload sends query chains rooted at a model: a model, a list of them or a column. */
final class FinderChainEvent implements ShouldBroadcast
{
    /** Carries the key of the user the payload finds. */
    public function __construct(public int $userId) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('finder-chain');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'find' => User::find($this->userId),
            'plain_find' => User::find(1),
            'pluck_first' => Post::query()->pluck('id')->first(),
            'where_get' => User::query()->where('id', 1)->get(),
        ];
    }
}
