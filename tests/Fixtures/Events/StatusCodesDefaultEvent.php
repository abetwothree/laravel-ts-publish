<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event whose untyped default holds an int key, which its payload sends as written. */
final class StatusCodesDefaultEvent implements ShouldBroadcast
{
    public $codes = [200 => 'OK'];

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('status-codes-default');
    }
}
