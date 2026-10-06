<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose first return is a filter, beside a variable it cannot read: the filter is read.
 *
 * @mixin Tag
 */
class ReturnedFilterThenOpaqueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return $this->only(['id', 'name']);
        }

        $data = $this->resource->toArray();
        $data['links'] = 1;

        return $data;
    }
}
