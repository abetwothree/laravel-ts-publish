<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A whenLoaded() closure whose variadic parameter collects a morphTo. The list holds whichever target loaded, never
 * null: Laravel calls the closure only for a loaded value that is not null. `reviewable` can load as null, so its key
 * also takes the `null` whenLoaded() returns; `imageable` cannot. The reviewable targets share a basename, so each
 * keeps its alias.
 *
 * @mixin Image
 */
class ImageSubjectsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subjects' => $this->whenLoaded('reviewable', fn (...$subjects) => $subjects),
            'owners' => $this->whenLoaded('imageable', fn (...$owners) => $owners),
        ];
    }
}
