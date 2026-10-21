<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DefaultTitlePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Column reads on a model with no attributes, which Laravel sends as null, beside unsaved copies that hold attributes,
 * and the relation, accessor and default-attribute reads a new model answers all the same.
 *
 * @mixin Post
 */
class AttributelessModelReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $draft = new Post;

        return [
            'new_title' => (new Post)->title,
            'default_title' => $this->when(true, fn ($post = new Post) => $post->title),
            'new_key' => (new Post)->getKey(),
            'replicated_title' => $this->resource->replicate()->title,
            'factory_name' => User::factory()->make()->name,
            'first_or_new_title' => Post::query()->firstOrNew(['title' => 'x'])->title,
            'filled_title' => (new Post(['title' => 'x']))->title,
            'local_title' => $draft->title,
            'new_status' => (new Post)->status,
            'new_reading_time' => (new Post)->reading_time,
            'new_comments' => (new Post)->comments,
            'default_attribute_title' => (new DefaultTitlePost)->title,
        ];
    }
}
