<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\ReadingSheet;

/**
 * Reads a sheet whose members the body types less than the `toArray()` `@return` docblock does (GH-154): a docblock
 * list with a `mixed` member, a map over an array with no element type, and array_filter() of a literal holding a
 * value with no type.
 *
 * @property-read ReadingSheet $resource
 */
class DeclaredReadingsResource extends JsonResource
{
    /**
     * @return array{
     *     entries: list<array{label: string|null}>,
     *     labels: list<array{label: string|null}>,
     *     flags: array{note?: string, weight?: int}
     * }
     */
    public function toArray(Request $request): array
    {
        $sheet = $this->resource;

        return [
            'entries' => $sheet->entries,
            'labels' => array_map(fn ($row) => ['label' => $row->label], $sheet->raw),
            'flags' => array_filter(['note' => $sheet->note, 'weight' => 1]),
        ];
    }
}
