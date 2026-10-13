<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A whenLoaded() closure parameter its body reassigns: a read past the reassignment takes what the body assigned,
 * and a read before it, past a branch that reassigns it and returns, or a reassignment to the same model, keeps the
 * parameter's own binding.
 *
 * @mixin Post
 */
class ReassignedParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'declared_content' => $this->whenLoaded('author', function (User $author) {
                /** @var Comment $author */
                $author = $author->comments->first();

                return $author->content;
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
            'exiting_branch' => $this->whenLoaded('author', function (User $author) {
                if ($author->name === 'x') {
                    $author = $author->profile;

                    return $author?->bio;
                }

                return $author->name;
            }),
            // `website` is the Profile's own column: a User has none.
            'new_model_name' => $this->whenLoaded('author', function (User $author) {
                $author = $author->profile;

                return $author?->website;
            }),
        ];
    }
}
