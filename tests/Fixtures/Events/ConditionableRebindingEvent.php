<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\KeyedBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose when() callables write a name the call's object or condition reads, each its own local. */
final class ConditionableRebindingEvent implements ShouldBroadcast
{
    use Conditionable;

    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-rebinding');
    }

    /**
     * The payload: each callable is passed the object and the value as the call reads them, before its body runs.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $assigned = $this->post->is_pinned;
        $looped = $this->post->is_pinned;
        $referenced = $this->post->is_pinned;
        $keyed = new KeyedBox;

        return [
            'assigned' => $this->when($assigned, function ($event, $value) {
                $assigned = 'str';

                return $value;
            }),
            'looped' => $this->when($looped, function ($event, $value) {
                foreach (['str'] as $looped) {
                }

                return $value;
            }),
            'receiver_assigned' => $keyed->when($this->post, function ($held) {
                $keyed = 5;

                return $held->id;
            }),
            'referenced' => $this->when($referenced, function ($event, $value) use (&$referenced) {
                $referenced = 'str';

                return $value;
            }),
        ];
    }
}
