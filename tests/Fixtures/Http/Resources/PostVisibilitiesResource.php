<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Wraps a post's nullable visibility at the top level, which filter() sends as null, and in a map kept as a Collection,
 * which serializes each wrap itself.
 *
 * @mixin Post
 */
class PostVisibilitiesResource extends JsonResource
{
    /**
     * The post's visibility, wrapped alone and in a kept map.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'visibility' => EnumResource::make($this->visibility),
            'visibilities' => collect([$this->resource])->map(fn (Post $post) => EnumResource::make($post->visibility)),
        ];
    }
}
