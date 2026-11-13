<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\KeyedBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose when() callables name or write a variable its object or condition reads, each its own. */
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
     * The payload: each callable is passed the object and the value as the call reads them, whatever its parameters
     * are named and whatever its body later writes.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $assigned = $this->post->is_pinned;
        $looped = $this->post->is_pinned;
        $referenced = $this->post->is_pinned;
        $keyed = new KeyedBox;
        $box = new ConditionableBox;
        $boxed = new ConditionableBox;
        $shadowed = $this->post->is_pinned;
        $rewritten = $this->post->is_pinned;
        $defaulted = $this->post->is_pinned;
        $redefaulted = $this->post->is_pinned;
        $valued = $this->post->is_pinned;
        $rested = $this->post->is_pinned;
        $counter = new KeyedBox;

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
            'receiver_shadow' => $box->when($this->post->is_pinned, fn ($box) => $box->name),
            'receiver_shadow_rewritten' => $boxed->when($this->post->is_pinned, function ($boxed) {
                $name = $boxed->name;
                $boxed = 5;

                return $name;
            }),
            'condition_shadow' => $this->when($shadowed, function ($event, $shadowed) {
                return $shadowed;
            }),
            'condition_shadow_rewritten' => $this->when($rewritten, function ($event, $rewritten) {
                $was = $rewritten;
                $rewritten = 'str';

                return $was;
            }),
            'default_shadow' => $this->when($defaulted, fn () => 'a', fn ($event, $defaulted) => $defaulted),
            'default_shadow_rewritten' => $this->when($redefaulted, fn () => 'a', function ($event, $redefaulted) {
                $was = $redefaulted;
                $redefaulted = 'str';

                return $was;
            }),
            'value_shadow' => $this->when($valued, fn ($valued, $value) => $value),
            'rest_shadow' => $this->when($rested, fn ($rested, ...$rest) => $rest),
            'receiver_second_shadow' => $counter->when($this->post, fn ($held, $counter) => $held->id),
        ];
    }
}
