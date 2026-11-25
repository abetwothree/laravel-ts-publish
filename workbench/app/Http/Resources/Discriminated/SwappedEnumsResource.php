<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Objects holding two same-named enums, one of them inside a nested value: each literal splits, every token naming its
 * own enum. Swapped at the top level, swapped nested with another text, and a shared key beside a dependent's nested
 * enum (GH-157, GH-76).
 *
 * @mixin Post
 */
class SwappedEnumsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'priority' => $this->priority,
            'stages' => match ($this->priority) {
                Priority::Low => ['primary' => $this->status, 'secondary' => CrmStatus::Lead],
                Priority::Medium => ['primary' => CrmStatus::Lead, 'secondary' => $this->status],
                default => null,
            },
            'meta' => [
                'priority' => $this->priority,
                'stages' => match ($this->priority) {
                    Priority::Low => ['primary' => $this->status, 'secondary' => CrmStatus::Lead],
                    Priority::Medium => ['primary' => CrmStatus::Lead, 'secondary' => $this->status, 'extra' => true],
                    default => null,
                },
            ],
            'shared' => [
                'status' => $this->status,
                'is_pinned' => $this->is_pinned,
                'lead' => $this->is_pinned ? ['stage' => CrmStatus::Lead] : null,
            ],
        ];
    }
}
