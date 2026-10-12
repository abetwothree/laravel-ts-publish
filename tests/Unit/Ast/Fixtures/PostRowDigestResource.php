<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Calls PostRowFormatter::row() with the post, as PostRowResource does, so the second read reuses the first's analysis.
 *
 * @mixin Post
 */
final class PostRowDigestResource extends JsonResource
{
    /**
     * The row, typed from the helper's body.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['row' => PostRowFormatter::row($this->resource)];
    }
}
