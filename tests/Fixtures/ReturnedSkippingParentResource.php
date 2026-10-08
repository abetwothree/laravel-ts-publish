<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only parent resource whose sweep skips a return it cannot read beside its literal one.
 *
 * @mixin Tag
 */
class ReturnedSkippingParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('raw')) {
            return $this->resource->toArray();
        }

        return ['id' => $this->id];
    }
}
