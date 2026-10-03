<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that publishes the mutator and the append of HandoverCrew through only().
 *
 * @mixin HandoverCrew
 */
class HandoverCrewOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'crew', 'standby']);
    }
}
