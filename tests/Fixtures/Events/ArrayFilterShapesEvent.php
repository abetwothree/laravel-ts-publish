<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event whose array_filter() of shapes reaches json_encode() with the keys PHP keeps. */
final class ArrayFilterShapesEvent implements ShouldBroadcast
{
    public string $title = 'T';

    public ?string $subtitle = null;

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('array-filter-shapes');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'shape' => array_filter(['a' => $this->title, 'b' => $this->subtitle]),
            'int_keyed' => array_filter([1 => $this->title, 2 => $this->subtitle]),
        ];
    }
}
