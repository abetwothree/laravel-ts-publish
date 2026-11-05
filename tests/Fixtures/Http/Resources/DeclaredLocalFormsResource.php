<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Locals an inline `@var` declares over values no rule types, in each form that names a receiver: a one-parameter
 * collection, keys of either kind, a bare `string` key and an alias naming a collection.
 *
 * @phpstan-type CommentList EloquentCollection<int, Comment>
 * @phpstan-type KeyedComments Collection<string, Comment>
 *
 * @extends JsonResource<Post>
 */
final class DeclaredLocalFormsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Collection<Comment> $oneParam */
        $oneParam = $this->opaque($this->resource->comments);
        /** @var Collection<int|string, Comment> $either */
        $either = $this->opaque($this->resource->comments->keyBy('content'));
        /** @var Collection<array-key, Comment> $anyKey */
        $anyKey = $this->opaque($this->resource->comments->keyBy('content'));
        /** @var Collection<string, Comment> $keyed */
        $keyed = $this->opaque($this->resource->comments->keyBy('content'));
        /** @var CommentList $aliased */
        $aliased = $this->opaque($this->resource->comments);
        /** @var ?CommentList $nullableAliased */
        $nullableAliased = $this->opaque($this->resource->comments);
        /** @var KeyedComments $keyedAlias */
        $keyedAlias = $this->opaque($this->resource->comments->keyBy('content'));

        return [
            'one_param_first' => $oneParam->first()?->content,
            'either' => $either,
            'either_all' => $either->all(),
            'either_first' => $either->first()?->content,
            'any_key_all' => $anyKey->all(),
            'keyed_all' => $keyed->all(),
            'aliased' => $aliased,
            'aliased_first' => $aliased->first()?->content,
            'nullable_aliased_first' => $nullableAliased?->first()?->content,
            'keyed_alias_all' => $keyedAlias->all(),
        ];
    }

    /** A value no rule types: it round-trips through serialize(), which the engine does not read. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
