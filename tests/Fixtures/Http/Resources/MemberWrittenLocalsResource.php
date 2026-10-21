<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Locals the body writes through a member or an offset once it assigns them: a model stays the one model, which Laravel
 * stores the write in, while an array the offset write extends no longer holds the literal assigned.
 *
 * @mixin Post
 */
final class MemberWrittenLocalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $draft = new Post;
        $draft->title = 'Draft';
        $draft['content'] = 'Body';
        $post = $this->resource;
        $post->author->name = 'Bob';
        $options = ['a' => 1];
        $options['b'] = 'x';
        $key = 'a';
        $counts = [];
        $counts[$key] = 1;

        return [
            'draft_title' => $draft->title,
            'draft_content' => $draft->content,
            'post_title' => $post->title,
            'author_name' => $post->author->name,
            'captured_title' => $this->when(true, function () use ($draft) {
                $draft->title = 'Again';

                return $draft->title;
            }),
            'options' => $options,
            'key' => $key,
        ];
    }
}
