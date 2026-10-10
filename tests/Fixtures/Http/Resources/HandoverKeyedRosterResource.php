<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HandoverKeyedRoster;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource that reads HandoverKeyedRoster's member. */
class HandoverKeyedRosterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['members' => (new HandoverKeyedRoster)->members];
    }
}
