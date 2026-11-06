<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableStatusBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose payload passes Conditionable's when() and unless() a callable string or pair. */
final class ConditionableCallableEvent implements ShouldBroadcast
{
    use Conditionable;

    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-callable');
    }

    /** A tag a `[$this, 'tag']` pair names. */
    public function tag(self $event, mixed $value): string
    {
        return 'T';
    }

    /**
     * The payload: each key the call a callable string or pair names, or the object itself.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pinned = (bool) $this->post->is_pinned;
        $box = new ConditionableBox;
        $pair = [$box, 'shout'];
        $named = 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label';

        return [
            'static_string' => $box->when($pinned, 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label'),
            'class_pair' => $box->when($pinned, [ConditionableBox::class, 'label']),
            'this_pair' => $this->when($pinned, [$this, 'tag']),
            'object_pair' => $box->when($pinned, [$box, 'shout']),
            'number_pair' => $box->when($pinned, [$box, 'size']),
            'unless_pair' => $box->unless($pinned, [$box, 'shout']),
            'held_pair' => $box->when($pinned, $pair),
            'held_string' => $box->when($pinned, $named),
            'named_pair' => $box->when(callback: [$box, 'shout'], value: $pinned),
            'default_string' => $box->when($pinned, fn () => 'x', 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label'),
            'unless_default_pair' => $box->unless($pinned, fn () => 7, [$box, 'shout']),
            'status_box' => (new ConditionableStatusBox)->when($pinned, fn () => 'x'),
        ];
    }
}
