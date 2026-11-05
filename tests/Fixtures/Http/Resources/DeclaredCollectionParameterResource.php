<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Helpers whose parameter only a `@param` collection generic types, read through `first()`, `map()` and `all()` in
 * each spelling: a first-class callable, a closure, and a direct call passed a typed or untyped value.
 *
 * @mixin Post
 */
class DeclaredCollectionParameterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'typed_callable_first' => $this->whenLoaded('comments', $this->typedFirst(...)),
            'native_callable_first' => $this->whenLoaded('comments', $this->nativeFirst(...)),
            'support_callable_first' => $this->whenLoaded('comments', $this->supportFirst(...)),
            'closure_first' => $this->whenLoaded('comments', /** @param Collection<int, Comment> $c */ function ($c) {
                return ['first' => $c->first()?->content];
            }),
            'untyped_arg_first' => $this->typedFirst($this->opaque($this->resource->comments)),
            'typed_arg_first' => $this->typedFirst($this->resource->comments),
            'map_contents' => $this->whenLoaded('comments', $this->typedMap(...)),
            'string_keyed_all' => $this->stringKeyedAll($this->resource->comments->keyBy('content')),
            'either_key_all' => $this->eitherKeyAll($this->resource->comments),
        ];
    }

    /**
     * The first comment's content, the parameter untyped.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function typedFirst($comments)
    {
        return ['first' => $comments->first()?->content];
    }

    /**
     * The first comment's content, the parameter natively an Eloquent collection.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function nativeFirst(Collection $comments)
    {
        return ['first' => $comments->first()?->content];
    }

    /**
     * The first comment's content, the parameter documented as a Support collection.
     *
     * @param  SupportCollection<int, Comment>  $comments
     */
    private function supportFirst($comments)
    {
        return ['first' => $comments->first()?->content];
    }

    /**
     * Each comment's content.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function typedMap($comments)
    {
        return $comments->map(fn ($c) => $c->content)->all();
    }

    /**
     * The comments under the string keys the declaration names.
     *
     * @param  Collection<string, Comment>  $comments
     */
    private function stringKeyedAll($comments)
    {
        return $comments->all();
    }

    /**
     * The comments under keys of either kind.
     *
     * @param  Collection<int|string, Comment>  $comments
     */
    private function eitherKeyAll($comments)
    {
        return $comments->all();
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
