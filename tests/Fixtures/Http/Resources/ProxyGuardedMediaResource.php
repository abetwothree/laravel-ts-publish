<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;
use Workbench\App\Models\Post;

/**
 * A post resource whose toArray() returns early unless it holds a media type, so the rest reads one.
 *
 * @mixin Post
 */
final class ProxyGuardedMediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof MediaType) {
            return [];
        }

        return [
            'max' => $this->maxSizeMb(),
            'max_via_resource' => $this->resource->maxSizeMb(),
            'prefixes' => $this->resource::mimePrefixes(),
        ];
    }
}
