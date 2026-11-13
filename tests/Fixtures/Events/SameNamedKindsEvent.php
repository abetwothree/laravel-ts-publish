<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\ValueObjects\SameNamedKindsPayload;

/** A test-only event with no broadcastWith(), whose one public property holds two same-named enums. */
final class SameNamedKindsEvent implements ShouldBroadcast
{
    public SameNamedKindsPayload $kinds;

    public function __construct()
    {
        $this->kinds = new SameNamedKindsPayload;
    }

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('same-named-kinds');
    }
}
