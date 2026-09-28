<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Exercises collection pipelines that must keep their element type to the end of the chain:
 * a trailing values()->all(), concat() of the same relation, a chain rooted at collect(),
 * and data_get() standing in for a nullsafe property chain.
 *
 * @mixin Post
 */
final class CollectionPipelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // all() is identity on the published type and keeps sequential keys, so the
            // mapped element type survives to the end of the chain.
            'comment_ids' => $this->comments->map(fn ($comment) => $comment->id)->values()->all(),

            // Rooted at collect(), not at a relation: the element type comes from the argument.
            'title_words' => collect(explode(' ', $this->title))->map(fn ($word) => ['word' => $word])->values()->all(),

            // data_get() with a literal key is the nullsafe chain $this->author?->name.
            'author_name' => data_get($this->author, 'name'),

            // The default only covers a MISSING key, so it unions in rather than removing null.
            'author_name_or_guest' => data_get($this->author, 'name', 'guest'),

            // concat() of the very same collection type is identity; a different one would decline.
            'doubled' => $this->comments->concat($this->comments)->values(),

            // The map receiver is a local variable, and the closure's own type hint names the element.
            'typed' => $this->when(true, function () {
                $items = $this->comments;

                return $items->map(fn (Comment $comment) => ['id' => $comment->id])->values()->all();
            }),
        ];
    }
}
