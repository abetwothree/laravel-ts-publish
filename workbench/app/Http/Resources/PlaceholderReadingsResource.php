<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\SampleSheet;

/**
 * Placeholders a dropped arm leaves: an empty list, and a null among out-of-order int keys (GH-154).
 *
 * @property-read SampleSheet $resource
 */
class PlaceholderReadingsResource extends JsonResource
{
    /**
     * @return array{
     *     fills: list<array{f: list<int>}>,
     *     pairs: list<array{0: int, 1: string|null}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'fills' => array_map(fn ($r) => ['f' => $r->ok ? cache('k') : []], $this->resource->rows),
            'pairs' => array_map(fn ($r) => [1 => $r->ok ? $r->hint : null, 0 => $r->hint], $this->resource->rows),
        ];
    }
}
