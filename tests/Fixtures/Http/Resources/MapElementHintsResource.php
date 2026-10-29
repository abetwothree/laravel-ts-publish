<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractKeyedModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\IntKeyedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Maps whose receiver a `?->` can end as null, and maps whose parameter declares a supertype of the element: the first
 * send null without an author, and the second read the element's own model.
 *
 * @mixin Post
 */
class MapElementHintsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'author_posts' => $this->author?->posts->map(fn ($post) => ['id' => $post->id]),
            'author_posts_all' => $this->author?->posts->map(fn ($post) => ['id' => $post->id])->all(),
            'model_hint_chain' => $this->comments->map(fn (Model $comment) => $comment->content),
            'model_hint_collect' => collect($this->comments)->map(fn (Model $comment) => $comment->content)->all(),
            'abstract_hint_query' => IntKeyedModel::query()->get()->map(fn (AbstractKeyedModel $post) => $post->title)->all(),
        ];
    }
}
