<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\NullableWrapBuilder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Grade;

/** A test-only event wrapping a grade that may be null in an `EnumResource`, alone or in a map, and a helper's wrap. */
final class NullEnumWrapEvent implements ShouldBroadcast
{
    public ?Grade $grade = null;

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('null-enum-wrap');
    }

    /**
     * The payload, which no JsonResource::filter() runs over.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'wrap' => EnumResource::make($this->grade),
            'kept' => collect([$this->grade])->map(fn ($g) => EnumResource::make($g)),
            'all' => collect([$this->grade])->map(fn ($g) => EnumResource::make($g))->all(),
            'helper' => NullableWrapBuilder::status(9),
        ];
    }
}
