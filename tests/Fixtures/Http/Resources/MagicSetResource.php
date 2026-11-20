<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Overrides `__set()` to clear its post's pin on every write of a member it does not declare.
 *
 * @mixin Post
 */
final class MagicSetResource extends JsonResource
{
    /**
     * Clears the post's pin.
     */
    public function __set($key, $value): void
    {
        $this->resource->is_pinned = false;
    }
}
