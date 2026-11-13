<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Relation filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * The method body's filters publish what the accessor's do, naming the models they hold.
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
            'summary_via_resource' => $this->resource->relationSummary(),
            'picks' => $this->relation_picks,
            'picks_summary' => $this->picksSummary(),
        ];
    }
}
