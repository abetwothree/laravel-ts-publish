<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DefaultTitlePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Column reads on a model with no attributes, which Laravel sends as null, beside copies holding attributes, new models
 * the body fills, a member write only that column, and the relation, accessor and default reads a new model answers.
 * A closure never called fills nothing, and a write its column cannot hold leaves the local unread.
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
        $parked = new Post;
        $fill = function () use ($parked) {
            $parked->title = 'x';
        };
        $shadowed = new Post;
        $save = fn ($shadowed) => $shadowed->title = 'x';
        $ran = new Post;
        collect([1])->each(function () use ($ran) {
            $ran->title = 'x';
        });
        $replaced = new Post;
        $replacedTitle = $replaced->title;
        $replaced = new Post;
        $replaced->title = 'x';
        $nulled = new Post;
        $nulled->title = null;
        $redacted = $this->resource->replicate();

        if (! $this->is_pinned) {
            $redacted->title = null;
        }

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
            'written_content' => $written->content,
            'nulled_title' => $nulled->title,
            'redacted_title' => $redacted->title,
            'offset_title' => $offset->title,
            'filled_local_title' => $filled->title,
            'exit_filled_title' => $this->when(true, function () {
                $post = new Post;

                if ($this->is_pinned === 'never') {
                    $post = new Post;

                    return 'never';
                }

                $post->fill(['title' => 'y']);

                return $post->title;
            }),
            'exit_written_title' => $this->when(true, function () {
                $post = new Post;

                if ($this->is_pinned === 'never') {
                    $post = new Post;

                    return 'never';
                }

                $post->title = 'w';

                return $post->title;
            }),
            'parked_title' => $parked->title,
            'shadowed_title' => $shadowed->title,
            'ran_title' => $ran->title,
            'replaced_title' => $replacedTitle,
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
            $this->mergeWhen(true, function ($post = new Post) {
                $post->title = 'z';

                return ['merged_title' => $post->title, 'merged_content' => $post->content];
            }),
        ];
    }
}
