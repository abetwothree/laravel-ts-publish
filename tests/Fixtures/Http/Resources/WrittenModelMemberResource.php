<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A parameter bound to a model, which the body replaces with one of its columns and then reads a member of: PHP reads
 * the member of a string, which Laravel throws on.
 *
 * @mixin Post
 */
final class WrittenModelMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'member_past' => $this->memberPast($this->resource),
        ];
    }

    /**
     * The title's own `title`, read past the write that replaced the post with its title.
     */
    private function memberPast(Post $post)
    {
        $post = $post->title;

        return $post->title;
    }
}
