<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose when() callables declare scalar parameters, which PHP converts the passed value to. */
final class ConditionableCoercedEvent implements ShouldBroadcast
{
    use Conditionable;

    public function __construct(public Post $post) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-coerced');
    }

    /**
     * The payload: Laravel's own code calls each callable, so a scalar parameter receives the value converted.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'to_int' => $this->when('5', fn ($event, int $value) => $value),
            'to_string' => $this->when(5, fn ($event, string $value) => $value),
            'to_bool' => $this->when($this->post->id, fn ($event, bool $value) => $value),
            'to_nullable_int' => $this->when('5', fn ($event, ?int $value) => $value),
            'null_to_nullable_int' => $this->when(null, fn () => 'a', fn ($event, ?int $value) => $value),
            'int_to_float' => $this->when(5, fn ($event, float $value) => $value),
            'untyped' => $this->when('5', fn ($event, $value) => $value),
            'to_int_list' => $this->when('5', fn (...$passed) => $passed),
            'to_int_rest' => $this->when('5', fn ($event, int ...$rest) => $rest),
        ];
    }
}
