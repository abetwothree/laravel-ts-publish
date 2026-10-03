<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource over HandoverCrew. It reads the accessors through `$this`, `crew` through a variable bound to the
 * model and through a relation's only(), and `crew` again under a key of an inline array that another key follows.
 *
 * @mixin HandoverCrew
 */
class HandoverCrewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var HandoverCrew $handover */
        $handover = $this->resource;

        return [
            'crew' => $this->crew,
            'standby' => $this->standby,
            'updated_at' => $this->updated_at,
            'bound_crew' => $handover->crew,
            'twin_crew' => $this->twin->only(['crew']),
            'keyed_crew' => ['crew' => $this->crew, 'sender' => $this->sender],
        ];
    }
}
