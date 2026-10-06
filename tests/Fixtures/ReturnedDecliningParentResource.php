<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only parent resource whose sweep declines, so the first-return fallback reads its guard and not the rest.
 *
 * @mixin Tag
 */
class ReturnedDecliningParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('empty')) {
            return [];
        }

        return $this->resource->toArray();
    }
}
