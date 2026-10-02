<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * Exposes a morphTo whose targets' resources share a basename: Crm's UserResource and this namespace's UserResource
 * must each be named by its own alias, inside an inline array as well.
 *
 * @mixin Image
 */
class ImageReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
            'reviewer' => $this->reviewable->toResource(),
            'review' => [
                'subject' => $this->reviewable->toResource(),
                'label' => $this->alt_text,
            ],
        ];
    }
}
