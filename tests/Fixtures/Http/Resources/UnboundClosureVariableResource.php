<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads inside whenLoaded() closures through variables the loaded relation does not bind. `$other` and `$others` are
 * undefined, so Laravel throws for them; the relation's model says nothing about what any of these variables hold.
 *
 * @mixin Post
 */
class UnboundClosureVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unbound_prop' => $this->whenLoaded('author', fn ($author) => $other->name),
            'unbound_method' => $this->whenLoaded('author', fn ($author) => $other->getKey()),
            'unbound_pluck' => $this->whenLoaded('comments', fn ($comments) => $others->pluck('content')),
            'local_post' => $this->whenLoaded('author', function ($author) {
                $post = $this->resource;

                return $post->title;
            }),
            'author_relations' => $this->whenLoaded('author', fn ($author) => ['posts' => $author->posts, 'profile' => $author->profile]),
            ...$this->relationReads($this->resource),
        ];
    }

    /**
     * Reads two of a post's relations through a model-typed parameter.
     *
     * @return array<string, mixed>
     */
    private function relationReads(Post $post): array
    {
        return ['s_author' => $post->author, 's_comments' => $post->comments];
    }
}
