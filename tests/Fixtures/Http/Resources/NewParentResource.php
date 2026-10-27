<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A `new parent` builds JsonResource, which publishes no type; `parent::make()` builds this class, as `new static`. */
class NewParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'built' => new parent($this->resource),
            'resolved' => (new parent($this->resource))->resolve(),
            'made' => parent::make($this->resource),
            'listed' => parent::collection([$this->resource]),
        ];
    }
}
