<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MapBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Maps over receivers that are no collection: each throws or runs its own map(), so none publishes a list of the
 * closure's shape. A paginator is the exception, since it forwards map() to the posts on its page.
 *
 * @mixin Post
 */
class MapReceiverEdgesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $box = new MapBox(['first' => $this->resource]);
        $literal = [$this->resource];
        $page = Post::query()->paginate();

        return [
            'box' => $box->map(fn (Post $post) => ['id' => $post->id]),
            'literal' => $literal->map(fn (Post $post) => ['id' => $post->id]),
            'paged' => $page->map(fn (Post $post) => ['id' => $post->id]),
        ];
    }
}
