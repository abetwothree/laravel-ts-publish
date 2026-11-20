<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Enum resources inside literals that print one object per arm (GH-157): beside a group, as a group's arm, two in its
 * arms, beside a same-named enum read directly, and in a ternary of two literals. Each wrap keeps its value import.
 *
 * @mixin Post
 */
class WrapArmsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'beside_group' => [
                'pinned' => $this->is_pinned,
                'until' => $this->when($this->is_pinned, $this->updated_at),
                'status' => EnumResource::make($this->status),
            ],
            'in_arm' => ['pinned' => $this->is_pinned, 'e' => $this->is_pinned ? EnumResource::make($this->status) : null],
            'two_wraps' => [
                'pinned' => $this->is_pinned,
                'e' => $this->is_pinned ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
            ],
            'beside_same_named' => [
                'pinned' => $this->is_pinned,
                'e' => EnumResource::make($this->status),
                'c' => CrmStatus::Lead,
                'v' => $this->is_pinned ? $this->id : null,
            ],
            'literals_ternary' => $this->published_at
                ? ['e' => EnumResource::make($this->status), 'x' => 1]
                : ['e' => null, 'x' => 'a'],
        ];
    }
}
