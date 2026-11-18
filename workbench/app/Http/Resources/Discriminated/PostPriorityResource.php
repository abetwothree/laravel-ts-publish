<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A nullable enum the resource sends picks two keys: a `match` with grouped cases and a `null` arm, and a `when()`
 * whose key is present for one case only (GH-157).
 *
 * @mixin Post
 */
class PostPriorityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priority' => $this->priority,
            'badge' => match ($this->priority) {
                Priority::Low, Priority::Medium => null,
                Priority::High => $this->title,
                Priority::Critical => new UserResource($this->user),
                null => [],
            },
            'escalated_at' => $this->when($this->priority === Priority::Critical, $this->updated_at),
        ];
    }
}
