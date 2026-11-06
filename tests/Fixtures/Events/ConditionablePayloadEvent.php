<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose payload reads Conditionable's when() and unless() on itself and on a value object. */
final class ConditionablePayloadEvent implements ShouldBroadcast
{
    use Conditionable;

    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-payload');
    }

    /** A label a first-class callable reads. */
    public function label(): string
    {
        return 'label';
    }

    /**
     * A label, or nothing for an unsaved post.
     *
     * @return string|void
     */
    public function labelOrNothing()
    {
        if ($this->post->id === 0) {
            return 'unsaved';
        }
    }

    /**
     * The payload: each key the callback's value, the default's, or the event itself where either gives null.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pinned = $this->post->is_pinned;

        return [
            'label' => $this->when($pinned, fn () => 'active'),
            'with_default' => $this->when($pinned, fn () => 'a', fn () => 'b'),
            'mixed_default' => $this->when($pinned, fn () => 'a', fn () => 1),
            'unless' => $this->unless($pinned, fn () => 'off'),
            'unless_default' => $this->unless($pinned, fn () => 'a', fn () => 2),
            'null_callback' => $this->when($pinned, fn () => null, fn () => 'b'),
            'null_only' => $this->when($pinned, fn () => null),
            'null_default' => $this->when($pinned, fn () => 'a', null),
            'named' => $this->when(value: $pinned, default: fn () => 2, callback: fn () => 'a'),
            'typed_callback' => $this->when($pinned, fn (): string => 'active'),
            'typed_self' => $this->when($pinned, fn (self $event) => $event->post->title),
            'passed_value' => $this->when($pinned, fn ($event, $value) => $value),
            'first_class' => $this->when($pinned, $this->label(...)),
            'variadic' => $this->when($pinned, fn (...$passed) => count($passed)),
            'variadic_list' => $this->when($pinned, fn (...$passed) => $passed),
            'mapped' => collect([1, 2])->map(fn (int $i) => $this->when($i > 1, fn () => 'big'))->all(),
            'box' => (new ConditionableBox)->when(true, fn () => 'boxed'),
            'box_param' => (new ConditionableBox)->when($pinned, fn (ConditionableBox $box) => $box->name),
            'box_itself' => (new ConditionableBox)->when($pinned, fn ($box) => $box),
            'void_or_default' => $this->when($pinned, fn () => $this->labelOrNothing(), fn () => 'b'),
        ];
    }
}
