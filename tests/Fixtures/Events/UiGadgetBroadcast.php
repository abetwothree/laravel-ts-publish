<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * A test-only event whose payload holds a value of a composed class.
 */
final class UiGadgetBroadcast implements ShouldBroadcast
{
    /** Takes the gadget its payload sends. */
    public function __construct(public Gadget $gadget) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('ui-gadget');
    }
}
