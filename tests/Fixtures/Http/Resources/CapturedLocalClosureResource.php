<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A whenLoaded() closure that reads a local of toArray() it captures, by `use` or as an arrow function. The local
 * holds the post, whatever relation the closure is loaded for.
 *
 * @mixin Post
 */
class CapturedLocalClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $post = $this->resource;

        return [
            'captured_use' => $this->whenLoaded('author', function ($author) use ($post) {
                return $post->title;
            }),
            'captured_arrow' => $this->whenLoaded('author', fn ($author) => $post->title),
        ];
    }
}
