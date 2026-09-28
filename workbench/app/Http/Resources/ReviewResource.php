<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Review;

/**
 * Exercises a morphTo closure parameter: $subject binds to every morph target, so toResource()
 * unions their resources and a plain attribute read unions the targets' own column types.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
            'reviewable_name' => $this->whenLoaded('reviewable', fn ($subject) => $subject->name),
        ];
    }
}
