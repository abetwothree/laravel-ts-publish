<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose toArray() holds a closure returning an array before its own `return $this->only(…)`.
 *
 * @mixin Post
 */
class ClosureReturnBeforeOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $format = function ($value) {
            return ['raw' => $value];
        };

        return $this->only(['id', 'title']);
    }
}
