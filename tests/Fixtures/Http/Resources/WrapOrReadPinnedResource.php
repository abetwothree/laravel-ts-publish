<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose one key following a boolean wraps an enum in one arm and reads it directly in the other.
 *
 * @mixin Post
 */
class WrapOrReadPinnedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            'stage' => $this->is_pinned ? EnumResource::make($this->status) : $this->status,
        ];
    }
}
