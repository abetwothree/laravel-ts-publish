<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\SampleSheet;

/**
 * Tags each row with a value no rule types or a string; BoundaryReadingsResource spreads it as its parent (GH-154).
 *
 * @property-read SampleSheet $resource
 */
class RowTagsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['tags' => array_map(fn ($r) => $r->ok ? cache('k') : 'a', $this->resource->rows), 'id' => $this->resource->id];
    }
}
