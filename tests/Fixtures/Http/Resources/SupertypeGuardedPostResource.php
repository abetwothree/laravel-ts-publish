<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads a helper's `Post` parameter, and a whenLoaded() collection, under `instanceof` tests for a class each already
 * is, `Model`, `Arrayable` or `Eloquent\Collection`, which prove nothing narrower than the binding.
 *
 * @mixin Post
 */
class SupertypeGuardedPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'model_guarded_title' => $this->modelGuardedTitle($this->resource),
            'model_guarded_word_count' => $this->modelGuardedWordCount($this->resource),
            'interface_guarded_title' => $this->interfaceGuardedTitle($this->resource),
            'model_ternary_title' => $this->modelTernaryTitle($this->resource),
            ...$this->modelGuardedFields($this->resource),
            'narrowed_contents' => $this->whenLoaded('comments', fn ($comments) => $comments instanceof EloquentCollection ? $comments->pluck('content') : null),
            'narrowed_spread' => $this->whenLoaded('comments', fn ($comments) => $comments instanceof EloquentCollection ? [...$comments->toArray()] : null),
        ];
    }

    /** The title, past a guard for the class every model is. */
    private function modelGuardedTitle(Post $post)
    {
        if (! $post instanceof Model) {
            return null;
        }

        return $post->title;
    }

    /** The word count, past a guard for the class every model is. */
    private function modelGuardedWordCount(Post $post)
    {
        if (! $post instanceof Model) {
            return null;
        }

        return $post->word_count;
    }

    /** The title, past a guard for an interface every model implements. */
    private function interfaceGuardedTitle(Post $post)
    {
        if (! $post instanceof Arrayable) {
            return null;
        }

        return $post->title;
    }

    /** The title, under a ternary test for the class every model is. */
    private function modelTernaryTitle(Post $post)
    {
        return $post instanceof Model ? $post->title : null;
    }

    /**
     * A spread of the title and a method's value, past a guard for the class every model is.
     *
     * @return array<string, mixed>
     */
    private function modelGuardedFields(Post $post): array
    {
        if (! $post instanceof Model) {
            return [];
        }

        return ['spread_title' => $post->title, 'spread_publishable' => $post->publishable()];
    }
}
