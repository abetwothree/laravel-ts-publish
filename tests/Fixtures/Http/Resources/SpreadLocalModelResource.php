<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Spreads `$variable->toArray()` of a variable no closure binds, inside a relation closure or a map body: each spreads
 * the model the variable holds, never the relation's or the element's. `unbound` reads a variable PHP never defines.
 *
 * @mixin Post
 */
class SpreadLocalModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $outer = $this->author;

        return [
            'local' => $this->whenLoaded('author', function () {
                $author = $this->author;

                return [...$author->toArray(), 'x' => 1];
            }),
            'captured' => $this->whenLoaded('author', function () use ($outer) {
                return [...$outer->toArray(), 'x' => 1];
            }),
            'collected_local' => collect($this->comments)->map(function ($comment) {
                $author = $comment->user;

                return [...$author->toArray(), 'x' => 1];
            })->all(),
            'relation_local' => $this->comments->map(function ($comment) {
                $author = $comment->user;

                return [...$author->toArray(), 'x' => 1];
            }),
            'fresh' => $this->whenLoaded('author', function () {
                $post = new Post;

                return [...$post->toArray(), 'x' => 1];
            }),
            'unbound' => $this->whenLoaded('tags', fn () => [...$nobody->toArray(), 'x' => 1]),
        ];
    }
}
