<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post's id, with a resource, its resolved body and a map kept as a Collection beside it in its response's `with()`.
 *
 * @mixin Post
 */
class HeldResourceWithResource extends JsonResource
{
    /**
     * The post's id.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /**
     * The keys beside the body: a resource over the post, as it serializes itself, its resolved body and a kept map.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'item' => PostVisibilitiesResource::make($this->resource),
            'resolved' => PostVisibilitiesResource::make($this->resource)->resolve(),
            'kept' => collect([$this->resource])->map(fn (Post $post) => EnumResource::make($post->visibility)),
        ];
    }
}
