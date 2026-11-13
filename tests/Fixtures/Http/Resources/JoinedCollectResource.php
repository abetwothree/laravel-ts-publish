<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A local holding the post's comments that a branch replaces with a value no rule types, wrapped in `collect()` past
 * the branch and mapped by a callback reading each comment, into a value and into an array.
 *
 * @mixin Post
 */
final class JoinedCollectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $comments = $this->comments;

        if ($this->is_pinned) {
            $comments = $this->opaque($this->comments);
        }

        return [
            'contents' => collect($comments)->map(fn ($comment) => $comment->content)->all(),
            'items' => collect($comments)->map(fn ($comment) => ['c' => $comment->content])->all(),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
