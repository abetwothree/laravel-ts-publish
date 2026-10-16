<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Supplier;

/** Reads a list `asort()` keys out of order in the arm an `instanceof` test on `$this->resource` proves. */
class SortedTagsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tags = ['b', 'a'];
        asort($tags);

        return ['tags' => $this->resource instanceof Supplier ? $tags : null];
    }
}
