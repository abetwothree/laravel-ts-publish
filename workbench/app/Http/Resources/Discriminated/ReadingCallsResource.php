<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * Literals whose items between a discriminant and its dependents only read: a method of the discriminant's own enum,
 * model methods the resource forwards, a relation check, a nested resource, a reading helper's callable, a key that
 * drops a value outside the group, reading calls inside an arm, a helper that reads, the model handed to a URL, a Gate
 * check and a resource, built with `new` or make(), a static helper, a reading helper's callable handed to
 * array_map(), when() handed a method's result on another read, a list of class names, and an untyped helper's
 * result handed on. Nothing changes the discriminant, so each literal should publish one object per variant
 * (GH-174).
 *
 * @mixin Post
 */
class ReadingCallsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enum_method' => [
                'status' => $this->status,
                'color' => $this->status->color(),
                'detail' => $this->status === Status::Published ? $this->title : null,
            ],
            'forwarded_key' => [
                'is_pinned' => $this->is_pinned,
                'key' => (int) $this->getKey(),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'forwarded_key_in_arm' => [
                'is_pinned' => $this->is_pinned,
                'key' => $this->is_pinned ? (int) $this->getKey() : null,
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'relation_check' => [
                'is_pinned' => $this->is_pinned,
                'loaded' => $this->resource->relationLoaded('author'),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'forwarded_check_in_when' => [
                'is_pinned' => $this->is_pinned,
                'author_title' => $this->when($this->relationLoaded('author'), $this->title),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'nested_resource' => [
                'is_pinned' => $this->is_pinned,
                'author' => new UserResource($this->whenLoaded('author')),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'reading_callable_in_when' => [
                'is_pinned' => $this->is_pinned,
                'label' => $this->when(true, $this->label(...)),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'dropper_outside_group' => [
                'id' => (int) $this->getKey(),
                'priority' => $this->priority,
                'detail' => $this->priority === Priority::Low ? $this->title : null,
                'stage' => match ($this->priority) {
                    Priority::Low, Priority::Medium, Priority::High, Priority::Critical => 'set',
                },
            ],
            'reading_call_in_arm' => [
                'is_pinned' => $this->is_pinned,
                'label' => $this->is_pinned ? $this->label() : null,
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'reading_helper' => [
                'is_pinned' => $this->is_pinned,
                'created' => $this->formatDate($this->created_at),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'url_of_model' => [
                'is_pinned' => $this->is_pinned,
                'url' => route('posts.show', $this->resource),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'gate_on_model' => [
                'is_pinned' => $this->is_pinned,
                'can_view' => (bool) Gate::allows('view', $this->resource),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'resource_of_model' => [
                'is_pinned' => $this->is_pinned,
                'card' => new UserResource($this->resource),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'resource_of_model_make' => [
                'is_pinned' => $this->is_pinned,
                'author' => UserResource::make($this->resource),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'callable_to_map' => [
                'is_pinned' => $this->is_pinned,
                'labels' => array_map($this->suffixed(...), ['a', 'b']),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'when_value_of_another_read' => [
                'is_pinned' => $this->is_pinned,
                'created' => $this->when($this->is_pinned, $this->created_at?->format('Y-m-d')),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'class_list_check' => [
                'is_pinned' => $this->is_pinned,
                'is_post' => in_array($this->resource::class, [Post::class, UserResource::class], true),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'untyped_helper_handed' => [
                'is_pinned' => $this->is_pinned,
                'shout' => strtoupper($this->untypedLabel()),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'static_reader' => [
                'is_pinned' => $this->is_pinned,
                'shout' => self::shout($this->title),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
        ];
    }

    /**
     * Upper-cases a value, writing nothing.
     */
    private static function shout(?string $value): string
    {
        return strtoupper((string) $value);
    }

    /**
     * The title as a label, writing nothing.
     */
    private function label(): string
    {
        return (string) $this->title;
    }

    /**
     * The title, held in a local, its signature typing nothing.
     */
    private function untypedLabel()
    {
        $label = (string) $this->title;

        return $label;
    }

    /**
     * A value with the title after it, writing nothing.
     */
    private function suffixed(string $value): string
    {
        return $value.$this->title;
    }

    /**
     * A date as `Y-m-d`, writing nothing.
     */
    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
