<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event whose payload writes a key, then spreads a shape whose `@return` declares that key optional. */
final class SpreadFoldDeclaredEvent implements ShouldBroadcast
{
    /** Holds the box and whether its shape is picked. */
    public function __construct(public FoldBox $box, public bool $picked = false) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('spread-fold');
    }

    /**
     * The payload: a key, then the declared shape over it.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['n' => 'x', ...$this->box->partial($this->picked)];
    }
}
