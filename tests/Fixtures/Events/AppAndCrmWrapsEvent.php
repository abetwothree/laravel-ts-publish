<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** A test-only event wrapping the app's and the CRM's Status, which print one const name (GH-160). */
final class AppAndCrmWrapsEvent implements ShouldBroadcast
{
    public function __construct(public bool $flag = false) {}

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('app-and-crm-wraps');
    }

    /**
     * The payload: two app wraps before a CRM one in a shape, and one of each in a ternary.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'pair' => [
                'a' => EnumResource::make(Status::Draft),
                'b' => EnumResource::make(Status::Published),
                'c' => EnumResource::make(CrmStatus::Lead),
            ],
            'either' => $this->flag ? EnumResource::make(Status::Draft) : EnumResource::make(CrmStatus::Lead),
        ];
    }
}
