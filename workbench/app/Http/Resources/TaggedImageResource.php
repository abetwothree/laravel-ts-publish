<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\TaggedImage;

/**
 * Reads a model's undocumented accessor that maps a cast array, under a `@return` typing it (GH-154).
 *
 * @mixin TaggedImage
 *
 * @property-read TaggedImage $resource
 */
class TaggedImageResource extends JsonResource
{
    /** @return array{tag_lists: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        return ['tag_lists' => $this->tag_lists, 'url' => $this->url];
    }
}
