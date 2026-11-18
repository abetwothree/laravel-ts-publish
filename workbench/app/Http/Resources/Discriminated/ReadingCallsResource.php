<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * Literals whose items between a discriminant and its dependents only read: a method of the discriminant's own enum,
 * model methods the resource forwards, a relation check, a nested resource, a reading helper's callable, a key that
 * drops a value outside the group, and reading calls inside an arm. Nothing changes the discriminant, so each literal
 * should publish one object per variant (GH-174).
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
        ];
    }

    /**
     * The title as a label, writing nothing.
     */
    private function label(): string
    {
        return (string) $this->title;
    }
}
