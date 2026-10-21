<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DraftPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads a helper's `Post` parameter under an `instanceof DraftPost` test: DraftPost casts `word_count` to a string,
 * where Post casts it to an integer, and adds a `shouted_title` accessor Post lacks.
 *
 * @mixin Post
 */
class NarrowedBoundPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->draftFields($this->resource)];
    }

    /**
     * The fields only a draft post sends.
     *
     * @return array<string, mixed>
     */
    private function draftFields(Post $post): array
    {
        return [
            'word_count' => $post instanceof DraftPost ? $post->word_count : null,
            'shouted_title' => $post instanceof DraftPost ? $post->shouted_title : null,
        ];
    }
}
