<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Services\CommentRowsService;

/**
 * Maps a post's comments through receivers other than `$this->comments`: a `collect()` root, a local holding the
 * relation, `getRelation()`, a key-breaking step, a query and a helper whose body maps them.
 *
 * @mixin Post
 */
class MappedCollectionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rows = $this->comments;
        $loaded = $this->resource->getRelation('comments');
        $byContent = $this->comments->keyBy('content');
        $sorted = $this->comments->sortBy('content');
        $mapped = $this->comments->map(fn (Comment $comment) => ['id' => $comment->id]);
        /** @var Collection<int, Comment> $declared */
        $declared = $this->resource->getRelationValue('comments');

        return [
            // A model element of a collect() root binds as the model, so its spread and its reads are the comment's.
            'collected_spread' => collect($this->comments)->map(fn (Comment $comment) => [...$comment->toArray(), 'flag' => true])->all(),
            'collected_reads' => collect($this->comments)->map(fn ($comment) => ['content' => $comment->content, 'who' => $comment->user?->name])->all(),
            'collected_spread_in_author' => $this->whenLoaded('author', fn () => collect($this->comments)->map(fn (Comment $comment) => [...$comment->toArray(), 'flag' => true])->all()),
            // An untyped parameter takes its element from the collection the local or getRelation() holds.
            'local_rows' => $rows->map(fn ($comment) => ['who' => $comment->user?->name]),
            'loaded_rows' => $loaded->map(fn ($comment) => $comment->only(['id', 'content'])),
            'local_proxy' => $rows->map->only(['id', 'content']),
            // A write that replaces the parameter is followed into the map body.
            'reassigned_parameter' => $rows->map(function (Comment $comment) {
                $comment = $comment->user;

                return $comment->name;
            }),
            // keyBy() and sortBy() break the keys, so the map is an object unless values() lists it again.
            'keyed_rows' => $byContent->map(fn (Comment $comment) => ['id' => $comment->id])->all(),
            'sorted_ids' => $sorted->map(fn ($comment) => $comment->id),
            'sorted_listed_ids' => $sorted->values()->map(fn ($comment) => $comment->id),
            'sorted_first' => $sorted->first()?->content,
            // A map reached through getRelation() inline, or held in a local, keeps its trailing values()->all().
            'loaded_values' => $this->resource->getRelation('comments')->map(fn ($comment) => ['id' => $comment->id])->values()->all(),
            'mapped_values' => $mapped->values()->all(),
            // A query's collection and a collection a `@var` declares name their element model.
            'query_rows' => Comment::query()->get()->map(fn (Comment $comment) => ['id' => $comment->id])->all(),
            'declared_first' => $declared->first()?->content,
            'all_posts' => Post::all()->toResourceCollection(),
            // A helper declared only `: array` publishes the shape its body maps.
            'post_rows' => resolve(CommentRowsService::class)->forPost($this->resource),
            'query_service_rows' => resolve(CommentRowsService::class)->all(),
            'unflagged_rows' => resolve(CommentRowsService::class)->unflagged(),
        ];
    }
}
