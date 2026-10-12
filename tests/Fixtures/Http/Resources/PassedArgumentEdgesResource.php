<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostSummaryFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Helpers read with what their calls pass: one passed two models, an argument nothing types, a recursion passing a new
 * value each time, a written parameter, a scalar body, a value naming a class the body fallback cannot import, a model
 * no row backs yet, and helpers declaring an abstract class or a union.
 *
 * @mixin Post
 */
final class PassedArgumentEdgesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'post_label' => $this->label($this->resource),
            'author_label' => $this->label($this->author),
            'unknown_label' => $this->label($request->input('subject')),
            'nested' => $this->nest($this->resource),
            'written' => $this->written($this->resource),
            'shout' => $this->whenHas('title', $this->shout(...)),
            'fallback' => $this->fallback($this->resource),
            'fallback_null' => $this->fallback(null),
        ];
    }

    /**
     * A model no row backs yet, passed to an untyped helper read as a value and spread.
     *
     * @return array<string, mixed>
     */
    public function unsaved(): array
    {
        return ['unsaved_row' => $this->keyed(new Post), ...$this->keyed(new Post)];
    }

    /**
     * Helpers declaring an abstract class or a union, passed a narrower class or a value nothing types.
     *
     * @return array<string, mixed>
     */
    public function declared(Request $request): array
    {
        return [
            'model_post' => $this->modelTitle($this->resource),
            'model_unknown' => $this->modelTitle($request->input('subject')),
            'by_model' => $this->whenLoaded('author', fn ($author) => [...$this->byModel($author)]),
            'by_union' => $this->whenLoaded('author', fn ($author) => [...$this->byUnion($author)]),
        ];
    }

    /**
     * A service helper whose parameter declares no type, called on the service a helper returns.
     *
     * @return array<string, mixed>
     */
    public function receivers(): array
    {
        return ['summary' => $this->formatter()->row($this->resource)];
    }

    /**
     * A first-class callable passed to an untyped helper, which may call it or not.
     *
     * @return array<string, mixed>
     */
    public function callables(): array
    {
        return ['held_callable' => $this->hold($this->resource->getKey(...))];
    }

    /**
     * @param  mixed  $subject
     * @return array<string, mixed>
     */
    private function label($subject): array
    {
        return ['title' => $subject->title, 'name' => $subject->name];
    }

    /**
     * Wraps the value in a list one level deeper at every call, so each call passes a new type.
     *
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function nest($value): array
    {
        return ['deeper' => $this->nest([$value])];
    }

    /**
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private function written($post): array
    {
        $post = $post->author;

        return ['author_name' => $post->name];
    }

    /**
     * @param  mixed  $title
     * @return mixed
     */
    private function shout($title)
    {
        return strtoupper($title);
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function fallback($value): array
    {
        return ['value' => $value ?? 'none'];
    }

    /**
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private function keyed($post): array
    {
        return ['k' => $post->getKey()];
    }

    /**
     * @param  mixed  $held
     * @return array<string, mixed>
     */
    private function hold($held): array
    {
        return ['held' => $held];
    }

    /**
     * The service the receivers() helper calls.
     */
    private function formatter(): PostSummaryFormatter
    {
        return new PostSummaryFormatter;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelTitle(Model $model): array
    {
        return ['title' => $model->title];
    }

    /**
     * @return array<string, mixed>
     */
    private function byModel(Model $model): array
    {
        return ['model_name' => $model->name];
    }

    /**
     * @return array<string, mixed>
     */
    private function byUnion(Post|User $owner): array
    {
        return ['union_name' => $owner->name];
    }
}
