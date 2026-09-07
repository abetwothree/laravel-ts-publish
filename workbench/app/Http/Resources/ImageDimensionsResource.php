<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * Both when() arms are inline objects whose members are nullable, so the union must be split at the
 * top level only.
 *
 * @mixin Image
 */
class ImageDimensionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'box' => $this->when(
                $this->height !== null,
                ['width' => $this->width, 'height' => $this->height],
                ['width' => $this->width],
            ),
        ];
    }
}
