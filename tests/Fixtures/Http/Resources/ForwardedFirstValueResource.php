<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A query's or relation's first() and firstWhere() find a model or null, so the value they hand on, bare or through a
 * helper, may be null, while a `?->` read of it carries that null itself.
 *
 * @mixin Post
 */
class ForwardedFirstValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'first' => $this->comments()->first(),
            'passed' => $this->passThrough($this->comments()->first()),
            'passed_first_where' => $this->passThrough($this->comments()->firstWhere('id', '>', 0)),
            'passed_query' => $this->passThrough(Post::query()->whereKey(0)->first()),
            'declared_nullable' => $this->nullableComment($this->comments()->first()),
            'first_content' => $this->comments()->first()?->content,
        ];
    }

    /**
     * The value it is given.
     */
    private function passThrough($value)
    {
        return $value;
    }

    /**
     * The comment it is given, or null.
     */
    private function nullableComment(?Comment $comment): ?Comment
    {
        return $comment;
    }
}
