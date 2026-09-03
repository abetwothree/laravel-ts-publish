<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A top-level spread InlineArrayHandler::classifySpreadArm() can't classify: no such relation. */
class DeclinedTopLevelSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->notARealRelation->toArray(),
            'id' => $this->id,
        ];
    }
}
