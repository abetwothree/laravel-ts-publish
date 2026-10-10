<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * Casts the key that reads two resources named `UserResource` to a type of the app's own, so neither resource is
 * imported.
 *
 * @mixin Image
 */
#[TsCasts(['reviewable' => ['type' => 'ReviewSubject | null', 'import' => '@js/types/reviews']])]
class ImageReviewCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource()),
        ];
    }
}
