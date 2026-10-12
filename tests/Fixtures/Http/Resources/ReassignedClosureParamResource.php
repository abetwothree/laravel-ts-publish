<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

/**
 * A model-bound closure parameter the closure body reassigns: each read past the reassignment takes what the body
 * assigned, not the parameter's old binding.
 *
 * @mixin Post
 */
class ReassignedClosureParamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // A Comment has no `name`, so Laravel sends null.
            'declared_name' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment $author */
                $author = $author->comments->first();

                return $author->name;
            }),
            'declared_content' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment $author */
                $author = $author->comments->first();

                return $author->content;
            }),
            'declared_nullsafe' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment $author */
                $author = $author->comments->first();

                return $author?->content;
            }),
            'declared_bare' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment $author */
                $author = $author->comments->first();

                return $author;
            }),
            'read_before' => $this->whenLoaded('author', function (User $author) {
                $name = $author->name;
                $author = $author->profile;

                return $name;
            }),
            'same_model' => $this->whenLoaded('author', function (User $author) {
                $author = $author->fresh() ?? $author;

                return $author->name;
            }),
            'relation_bio' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author?->bio;
            }),
            // A User has no `website`, so only the Profile the body assigned types it.
            'relation_website' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author?->website;
            }),
            'relation_bare' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author;
            }),
            'narrowed_bio' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author instanceof Profile ? $author->bio : null;
            }),
            'narrowed_website' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author instanceof Profile ? $author->website : null;
            }),
            // A User has no `content`, so Laravel sends null for each comment.
            'mapped_reassigned' => $this->whenLoaded('comments', fn ($comments) => $comments->map(function (Comment $comment) {
                $comment = $comment->user;

                return $comment->content;
            })),
            // `first()` names no class, so the old binding stands, though Laravel sends null (a Comment has no `name`).
            'unnamed_name' => $this->whenLoaded('author', function (User $author) {
                $author = $author->comments->first();

                return $author->name;
            }),
        ];
    }
}
