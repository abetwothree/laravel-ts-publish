<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * Exercises: whenNotNull on multiple nullable columns.
 *
 * @mixin Image
 */
class ImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt_text' => $this->alt_text,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->whenNotNull($this->width),
            'height' => $this->whenNotNull($this->height),
        ];
    }
}
