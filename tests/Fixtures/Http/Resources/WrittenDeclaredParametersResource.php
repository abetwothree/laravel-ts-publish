<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\PostStats;

/**
 * Parameters holding a class beside a value, which the body replaces with a value naming no class, read past the write
 * and copied before it: a helper's that declares a class, a transform() callback's passed a relation, and an untyped
 * helper's passed a model no row backs yet. A parameter bound to a model alone is written the same ways.
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
            'author_name' => $this->transform($this->author, function ($author) {
                $author = $this->same($author);

                return $author->name;
            }),
            'replica_title' => $this->titleThrough($this->resource->replicate()),
            'model_title' => $this->modelTitle($this->resource),
            'model_copied' => $this->modelCopied($this->resource),
            'model_decoded' => $this->modelDecoded($this->resource),
            'model_through' => $this->modelThrough($this->resource),
            'loaded_name' => $this->whenLoaded('author', function (User $author) {
                $author = $author->name;

                return $author;
            }),
        ];
    }

    /**
     * Hands back what it is given, with no type the package reads.
     */
    public function same(mixed $value): mixed
    {
        return func_get_args()[0];
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

    /**
     * The post's title, read through the parameter that held the post.
     */
    private function modelTitle(Post $post)
    {
        $post = $post->title;

        return $post;
    }

    /**
     * The post as it was passed, copied before the body writes the parameter.
     */
    private function modelCopied(Post $post)
    {
        $copy = $post;
        $post = $post->title;

        return $copy;
    }

    /**
     * A value no rule types, written over the post.
     */
    private function modelDecoded(Post $post)
    {
        $post = json_decode('{}');

        return $post;
    }

    /**
     * The title read through the post, past a write the package cannot type.
     */
    private function modelThrough(Post $post)
    {
        $post = $this->same($post);

        return $post->title;
    }

    /**
     * The title read through an untyped parameter, past a write the package cannot type.
     */
    private function titleThrough($post)
    {
        $post = $this->same($post);

        return $post->title;
    }
}
