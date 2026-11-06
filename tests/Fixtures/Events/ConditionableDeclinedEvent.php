<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ConditionablePost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\KeyedBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\OwnWhenBox;
use Closure;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose payload holds when() calls that are not Conditionable's two-callback form. */
final class ConditionableDeclinedEvent implements ShouldBroadcast
{
    use Conditionable;

    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-declined');
    }

    /** Decodes a list where the flag holds, which no reader types. */
    public function decodedOrNull(bool $flag)
    {
        return $flag ? json_decode('[1]') : null;
    }

    /**
     * A shape, or anything.
     *
     * @return array{a: string}|mixed
     */
    public function shapeOrAnything()
    {
        return ['a' => 'x'];
    }

    /**
     * The payload: a proxy, a class's own when(), a model's, a Conditionable model's own, a query's, a callback no
     * reader types or reads with an arm left out, one returning a closure, one whose parameter shadows a name the
     * receiver or condition reads, and an object whose JSON none does.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pinned = $this->post->is_pinned;
        $keyed = new KeyedBox;

        return [
            'proxy' => $this->when($pinned),
            'own_when' => (new OwnWhenBox)->when($pinned, 2),
            'model' => $this->post->when($pinned, fn ($query) => $query->where('id', 1)),
            'conditionable_model' => (new ConditionablePost)->when($pinned, fn () => 'a', fn () => 'b'),
            'query' => Post::query()->when($pinned, fn ($query) => $query->latest()),
            'query_values' => Post::query()->when($pinned, fn () => 'a', fn () => 'b'),
            'queried' => Post::query()->when($pinned, fn ($query) => $query->latest())->get(),
            'listed' => collect(['a'])->when($pinned, fn ($items) => $items->push('b')),
            'opaque' => $this->when($pinned, fn () => json_decode('[]')),
            'fluent' => (new Fluent)->when($pinned, fn () => 'a'),
            'unknown_arm' => $this->when($pinned, fn () => $this->shapeOrAnything()),
            'dropped_arm' => $this->when($pinned, fn (): ?object => $pinned ? json_decode('{"a":1}') : null),
            'dropped_in_helper' => $this->when($pinned, fn () => $this->decodedOrNull($pinned)),
            'uncalled_closure' => $this->when($pinned, fn (): ?Closure => $pinned ? fn () => 1 : null),
            'shadowed_value' => $this->when($pinned, fn ($pinned, $value) => $value),
            'shadowed_rest' => $this->when($pinned, fn ($pinned, ...$rest) => $rest),
            'shadowed_receiver' => $keyed->when($this->post, fn ($held, $keyed) => $held->id),
        ];
    }
}
