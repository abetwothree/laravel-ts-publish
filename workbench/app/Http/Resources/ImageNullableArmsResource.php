<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * Ternary and Elvis arms that are each nullable: the union must carry one trailing null.
 *
 * @mixin Image
 */
class ImageNullableArmsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'size' => $request->boolean('wide') ? $this->width : $this->alt_text,
            'label' => $this->alt_text ?: $this->height,
        ];
    }
}
