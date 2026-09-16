<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Relation filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * The method body's shape carries no import, so its filters publish types that name no model or enum.
 *
 * @mixin Comment
 */
final class CommentRelationFiltersResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => $this->relationSummary(),
            'picks' => $this->relation_picks,
        ];
    }
}
