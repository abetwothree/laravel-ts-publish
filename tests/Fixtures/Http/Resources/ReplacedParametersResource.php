<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Closure parameters a top-level write replaces: a pluck() or a map() leaves values other than the loaded comments, a
 * keyBy() or sortByDesc() the comments under moved keys, find() of one key a comment, and json_decode() or trim() a
 * value of its own.
 *
 * @mixin Post
 */
class ReplacedParametersResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'plucked_ids' => $this->whenLoaded('comments', function ($comments) {
                $comments = $comments->pluck('id');

                return $comments->map(fn ($id) => $id)->all();
            }),
            'mapped_contents' => $this->whenLoaded('comments', function ($comments) {
                $comments = $comments->map(fn ($comment) => $comment->content);

                return $comments->map(fn ($content) => $content)->all();
            }),
            'keyed_all' => $this->whenLoaded('comments', function ($comments) {
                $comments = $comments->keyBy('content');

                return $comments->all();
            }),
            'resorted_ids' => $this->whenLoaded('comments', function ($comments) {
                $comments = $comments->sortByDesc('id');

                return $comments->map(fn ($comment) => $comment->id);
            }),
            'merged_contents' => $this->whenLoaded('comments', function ($comments) {
                $comments = $comments->merge([]);

                return $comments->map(fn ($comment) => $comment->content);
            }),
            'found_content' => $this->whenLoaded('author', function (User $author) {
                $author = $author->comments->find(1);

                return $author?->content;
            }),
            'found_name' => $this->whenLoaded('author', function (User $author) {
                $author = $author->comments->find(1);

                return $author->name;
            }),
            // Only a member read names the comment: find()'s value, as first()'s on a variable, publishes unknown.
            'found_bare' => $this->whenLoaded('author', function (User $author) {
                $author = $author->comments->find(1);

                return $author;
            }),
            'decoded' => $this->whenHas('title', function ($title) {
                $title = json_decode($title);

                return $title;
            }),
            'trimmed' => $this->whenHas('title', function ($title) {
                $title = trim($title);

                return $title;
            }),
        ];
    }
}
