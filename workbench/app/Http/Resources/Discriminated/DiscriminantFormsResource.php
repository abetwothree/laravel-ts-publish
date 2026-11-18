<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Post;

/**
 * Nested literals whose keys follow a sibling key's value, one condition form per key (GH-157). Each `*_kept` key is
 * a control that stays one object: its test reads no sibling with a finite set of values, or cannot be decided.
 *
 * @mixin Post
 */
class DiscriminantFormsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'negated' => ['pinned' => $this->is_pinned, 'v' => ! $this->is_pinned ? $this->published_at : $this->id],
            'strict_true' => ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null],
            'strict_false' => ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === false ? $this->rating : $this->id],
            'case_compare' => ['status' => $this->status, 'v' => $this->status === Status::Published ? $this->published_at : null],
            'case_not_equal' => ['status' => $this->status, 'v' => $this->status !== Status::Draft ? $this->title : $this->id],
            'value_read' => [
                'status' => $this->status->value,
                'v' => match ($this->status) {
                    Status::Draft => $this->updated_at,
                    Status::Published => $this->id,
                },
            ],
            'unit_enum_default' => [
                'visibility' => $this->visibility,
                'v' => match ($this->visibility) {
                    Visibility::Public => $this->title,
                    default => null,
                },
            ],
            'match_true' => [
                'status' => $this->status,
                'v' => match (true) {
                    $this->status === Status::Draft => $this->updated_at,
                    default => $this->id,
                },
            ],
            'when_dependent' => ['pinned' => $this->is_pinned, 'until' => $this->when($this->is_pinned, $this->updated_at)],
            'when_closure' => ['pinned' => $this->is_pinned, 'until' => $this->when($this->is_pinned, fn () => $this->id)],
            'unless_default' => ['pinned' => $this->is_pinned, 'v' => $this->unless($this->is_pinned, $this->published_at, $this->id)],
            'two_dependents' => [
                'a' => $this->is_pinned ? $this->title : null,
                'pinned' => $this->is_pinned,
                'b' => $this->is_pinned ? null : $this->rating,
            ],
            'nested_ternary' => [
                'pinned' => $this->is_pinned,
                'v' => $this->is_pinned ? $this->title : ($this->rating !== null ? $this->rating : $this->id),
            ],
            'two_discriminants' => [
                'pinned' => $this->is_pinned,
                'status' => $this->status,
                'a' => $this->is_pinned ? $this->title : null,
                'b' => $this->status === Status::Draft ? $this->updated_at : $this->id,
            ],
            'throw_arm' => [
                'priority' => $this->priority,
                'v' => match ($this->priority) {
                    Priority::Low => $this->title,
                    Priority::High => $this->id,
                    default => throw new LogicException('Unsupported priority.'),
                },
            ],
            'shared_keys' => [
                'id' => $this->id,
                'pinned' => $this->is_pinned,
                'v' => $this->is_pinned ? $this->title : $this->id,
                'title' => $this->title,
            ],
            'cast_discriminant_kept' => ['pinned' => (bool) $this->is_pinned, 'v' => $this->is_pinned ? $this->title : null],
            'method_condition_kept' => ['featured' => $this->isFeatured(), 'v' => $this->isFeatured() ? $this->title : null],
            'unpublished_condition_kept' => ['id' => $this->id, 'v' => $this->is_pinned ? $this->title : null],
            'string_discriminant_kept' => ['title' => $this->title, 'v' => $this->title === 'x' ? $this->id : null],
            'wrapped_discriminant_kept' => [
                'status' => EnumResource::make($this->status),
                'v' => $this->status === Status::Draft ? $this->id : null,
            ],
            'optional_discriminant_kept' => [
                'pinned' => $this->when($this->id > 1, $this->is_pinned),
                'v' => $this->is_pinned ? $this->title : null,
            ],
            'undecidable_kept' => ['pinned' => $this->is_pinned, 'v' => $this->is_pinned && $this->title !== '' ? $this->title : null],
            'same_arms_kept' => ['pinned' => $this->is_pinned, 'v' => $this->is_pinned ? $this->id : $this->user_id],
            'untypable_arm_kept' => ['pinned' => $this->is_pinned, 'v' => $this->is_pinned ? json_decode($this->title) : null],
        ];
    }
}
