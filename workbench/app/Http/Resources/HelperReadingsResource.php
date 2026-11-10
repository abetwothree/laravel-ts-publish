<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\SampleSheet;

/**
 * Undocumented helpers that drop an arm, one spread and one read as a value, under a `@return` typing them (GH-154).
 *
 * @property-read SampleSheet $resource
 */
class HelperReadingsResource extends JsonResource
{
    /**
     * @return array{
     *     rows: list<array{name: string|int}>,
     *     sections: array{items: list<array{t: list<int>|string}>}
     * }
     */
    public function toArray(Request $request): array
    {
        return [...$this->part(), 'sections' => $this->sections()];
    }

    /**
     * Each row's name, or a value no rule types.
     */
    private function part(): array
    {
        return ['rows' => array_map(fn ($r) => ['name' => $r->x ?? cache('k')], $this->resource->rows)];
    }

    /**
     * Each row's list, or a value no rule types.
     */
    private function sections(): array
    {
        return ['items' => array_map(fn ($r) => ['t' => $r->ok ? [1, 2] : cache('k')], $this->resource->rows)];
    }
}
