<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource that reads a built base's summary through a receiver it declares as a subclass. */
final class BuiltLabelHolderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['fresh' => $this->labels()->freshSummary()];
    }

    /** The label resource, which may be any subclass of the one it declares. */
    public function labels(): BuiltLabelResource
    {
        return new BuiltLabelResource($this->resource);
    }
}
