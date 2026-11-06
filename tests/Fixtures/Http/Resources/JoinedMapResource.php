<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A local holding the post's comments that a branch replaces with a value no rule types, mapped past the branch by a
 * callback reading each comment, straight and past a step that keeps the items.
 *
 * @mixin Post
 */
final class JoinedMapResource extends JsonResource
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
            'contents' => $comments->map(fn ($comment) => $comment->content),
            'sorted_contents' => $comments->sortBy('id')->map(fn ($comment) => $comment->content)->values(),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
