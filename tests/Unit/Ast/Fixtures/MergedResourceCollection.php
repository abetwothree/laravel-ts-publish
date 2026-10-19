<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A collection merging its own resource, which Laravel serializes as the list of collected items. */
final class MergedResourceCollection extends ResourceCollection
{
    /** @return array<array-key, mixed> */
    public function toArray(Request $request): array
    {
        return ['meta' => 1, $this->merge($this->resource)];
    }
}
