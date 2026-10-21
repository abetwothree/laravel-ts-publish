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
 * new models the body writes or fills, and the relation, accessor and default-attribute reads a new model answers.
 *
 * @mixin Post
 */
class AttributelessModelReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $draft = new Post;
        $written = new Post;
        $written->title = 'x';
        $offset = new Post;
        $offset['title'] = 'w';
        $filled = new Post;
        $filled->fill(['title' => 'y']);

        return [
            'new_title' => (new Post)->title,
            'default_title' => $this->when(true, fn ($post = new Post) => $post->title),
            'new_key' => (new Post)->getKey(),
            'replicated_title' => $this->resource->replicate()->title,
            'factory_name' => User::factory()->make()->name,
            'first_or_new_title' => Post::query()->firstOrNew(['title' => 'x'])->title,
            'filled_title' => (new Post(['title' => 'x']))->title,
            'local_title' => $draft->title,
            'written_title' => $written->title,
            'offset_title' => $offset->title,
            'filled_local_title' => $filled->title,
            'default_written_title' => $this->when(true, function ($post = new Post) {
                $post->title = 'z';

                return $post->title;
            }),
            'default_offset_title' => $this->when(true, function ($post = new Post) {
                $post['title'] = 'v';

                return $post->title;
            }),
            'new_status' => (new Post)->status,
            'new_reading_time' => (new Post)->reading_time,
            'new_comments' => (new Post)->comments,
            'default_attribute_title' => (new DefaultTitlePost)->title,
        ];
    }
}
