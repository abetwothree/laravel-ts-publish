<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Enumerable;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;

/**
 * Helpers whose parameter a `@param` collection generic types, read through `first()`, `map()` and `all()` in each
 * spelling: a first-class callable, a closure, and a direct call passed a typed or untyped value, whose known
 * collection outranks the declaration, a narrower or another model's and its keys included.
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
            'keyed_list_all' => $this->listedAll($this->resource->comments->keyBy('content')),
            'lying_post_id' => $this->postsPostId($this->resource->comments),
            'lying_value' => $this->postsValue($this->resource->comments),
            'narrowed_subscriber' => $this->when(false, fn () => $this->teamSubscriber(SubscribedTeam::all())),
            'support_arg_only' => $this->listedOnly(collect($this->opaque($this->resource->comments->all()))),
            'support_keyed_arg_all' => $this->listedAll(collect($this->opaque($this->resource->comments->keyBy('content')->all()))),
            'untyped_support_arg_only' => $this->untypedOnly(collect($this->opaque($this->resource->comments->all()))),
            'enumerable_arg_only' => $this->nativeOnly($this->enumerableComments()),
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

    /**
     * The comments, under whatever keys the argument holds.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function listedAll(Enumerable $comments)
    {
        return $comments->all();
    }

    /**
     * The first item's `post_id`, the declaration naming another model than the argument holds.
     *
     * @param  Collection<int, Post>  $posts
     */
    private function postsPostId($posts)
    {
        return ['post_id' => $posts->first()?->post_id];
    }

    /**
     * The collection as passed, the declaration naming another model than the argument holds.
     *
     * @param  Collection<int, Post>  $posts
     */
    private function postsValue($posts)
    {
        return $posts;
    }

    /**
     * The first team's subscriber, a relation only the narrower model the argument holds declares.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function teamSubscriber(Enumerable $teams)
    {
        return ['sub' => $teams->first()?->subscriber];
    }

    /**
     * The comment under key 1, by the class the argument holds.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function listedOnly(Enumerable $comments)
    {
        return $comments->only([1]);
    }

    /**
     * The comment under key 1, the parameter untyped.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function untypedOnly($comments)
    {
        return $comments->only([1]);
    }

    /**
     * The comment whose primary key is 5, by the class the native type names.
     *
     * @param  Collection<int, Comment>  $comments
     */
    private function nativeOnly(Collection $comments)
    {
        return $comments->only([5]);
    }

    /**
     * The comments, declared wider than the collection they are.
     *
     * @return Enumerable<int, Comment>
     */
    private function enumerableComments(): Enumerable
    {
        return $this->resource->comments;
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
