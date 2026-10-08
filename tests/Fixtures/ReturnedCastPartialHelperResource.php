<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose lone returned variable starts from a helper it cannot read, under a method-level cast.
 *
 * @mixin Tag
 */
class ReturnedCastPartialHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['meta' => 'Record<string, string>'])]
    public function toArray(Request $request): array
    {
        $data = $this->basics();
        $data['meta'] = $this->resource->getAttribute('x');

        return $data;
    }

    /** @return array<string, mixed> */
    protected function basics(): array
    {
        return $this->resource->toArray();
    }
}
