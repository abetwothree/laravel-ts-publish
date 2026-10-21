<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PostStats;

/**
 * Helpers whose parameter declares a class and binds a value beside it, which the body replaces with a value naming no
 * class: read past the write, and copied before it.
 *
 * @mixin Post
 */
final class WrittenDeclaredParametersResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title_of' => $this->titleOf($this->resource),
            'copied' => $this->copied($this->resource),
            'decoded' => $this->decoded($this->resource),
            'views_of' => $this->viewsOf(new PostStats(1.0, 2)),
        ];
    }

    /**
     * The post's title, read through the parameter that held the post.
     */
    private function titleOf(?Post $post)
    {
        $post = $post?->title;

        return $post;
    }

    /**
     * The post as it was passed, copied before the body writes the parameter.
     */
    private function copied(?Post $post)
    {
        $copy = $post;
        $post = $post?->title;

        return $copy;
    }

    /**
     * A value no rule types, written over the post.
     */
    private function decoded(?Post $post)
    {
        $post = json_decode('{}');

        return $post;
    }

    /**
     * The view count, read through the parameter that held the stats.
     */
    private function viewsOf(PostStats $stats)
    {
        $stats = $stats->views;

        return $stats;
    }
}
