<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Numeric keys in groups that follow a sibling key's value (GH-157): one as a dependent, one as the discriminant, at
 * the top level and inside a nested literal. Each keeps its quoted name in every arm.
 *
 * @mixin Post
 */
class NumericKeysResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            '0' => $this->is_pinned ? $this->title : null,
            '7' => $this->status,
            'note' => $this->status === Status::Draft ? $this->title : $this->id,
            'nested' => [
                'pinned' => $this->is_pinned,
                '0' => $this->is_pinned ? $this->title : null,
                '7' => $this->status,
                'note' => $this->status === Status::Draft ? $this->title : $this->id,
            ],
        ];
    }
}
