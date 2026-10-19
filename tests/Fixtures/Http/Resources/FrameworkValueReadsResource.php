<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `with()` reads its own `resolve()` and `jsonSerialize()` as values, each its `toArray()`.
 *
 * @mixin Tag
 */
class FrameworkValueReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['snapshot' => $this->resolve($request), 'serialized' => $this->jsonSerialize()];
    }
}
