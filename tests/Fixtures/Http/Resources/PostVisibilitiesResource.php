<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Wraps a post's nullable visibility in its body, in a map kept as a Collection there, and in its response's `with()`.
 *
 * filter() sends the body's own wrap around null as null; the kept map and `with()` send each wrap as it serializes.
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

    /**
     * The post's visibility, wrapped beside the body in its response.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['meta' => EnumResource::make($this->visibility)];
    }
}
