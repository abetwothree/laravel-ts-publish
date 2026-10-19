<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * A resource over a post whose parent redeclares `$resource` as any model.
 *
 * @mixin Post
 */
final class ProxyModelPostResource extends ProxyModelResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'post' => $this->resource,
            'comments_count' => $this->commentsCount(),
            'key' => $this->getKey(),
        ];
    }
}
