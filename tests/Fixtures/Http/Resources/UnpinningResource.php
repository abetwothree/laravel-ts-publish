<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose own constructor clears the pin of the post it is handed, so building it may change the post.
 *
 * @mixin Post
 */
class UnpinningResource extends JsonResource
{
    /**
     * Clears the post's pin, then holds it.
     */
    public function __construct(Post $resource)
    {
        $resource->is_pinned = false;

        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }
}
