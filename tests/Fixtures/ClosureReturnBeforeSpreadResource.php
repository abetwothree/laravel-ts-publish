<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that spreads a helper holding a closure returning an array before its own
 * `return $this->only(…)`.
 *
 * @mixin Post
 */
class ClosureReturnBeforeSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->summary()];
    }

    /** @return array<string, mixed> */
    protected function summary(): array
    {
        $format = function ($value) {
            return ['raw' => $value];
        };

        return $this->only(['id', 'title']);
    }
}
