<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;

/**
 * Arms that spell one text, each binding the two same-named resources in the other order: Low and Medium stay apart,
 * at the top level and nested (GH-157).
 *
 * @mixin Post
 */
class SwappedResourcesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'priority' => $this->priority,
            'pair' => match ($this->priority) {
                Priority::Low => ['primary' => new UserResource($this->author), 'secondary' => new CrmUserResource($this->author)],
                Priority::Medium => ['primary' => new CrmUserResource($this->author), 'secondary' => new UserResource($this->author)],
                default => null,
            },
            'meta' => [
                'priority' => $this->priority,
                'pair' => match ($this->priority) {
                    Priority::Low => ['primary' => new UserResource($this->author), 'secondary' => new CrmUserResource($this->author)],
                    Priority::Medium => ['primary' => new CrmUserResource($this->author), 'secondary' => new UserResource($this->author)],
                    default => null,
                },
            ],
        ];
    }
}
