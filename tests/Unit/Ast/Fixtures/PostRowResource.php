<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Calls PostRowFormatter's helpers with the post twice and its author once, and its concretely typed one with the post
 * and a new post.
 *
 * @mixin Post
 */
final class PostRowResource extends JsonResource
{
    /**
     * Each row, typed from the helper's body.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'first' => PostRowFormatter::row($this->resource),
            'again' => PostRowFormatter::row($this->resource),
            'author' => PostRowFormatter::row($this->author),
            'typed' => PostRowFormatter::typed($this->resource),
            'typed_author' => PostRowFormatter::typed($this->author),
            'titled' => PostRowFormatter::titled($this->resource),
            'titled_new' => PostRowFormatter::titled(new Post),
        ];
    }
}
