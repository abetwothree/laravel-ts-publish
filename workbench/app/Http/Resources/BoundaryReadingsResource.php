<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\ValueObjects\SampleSheet;

/**
 * Spreads a custom parent's `toArray()` that drops an arm beside a string, under a `@return` typing both (GH-154).
 *
 * @property-read SampleSheet $resource
 */
class BoundaryReadingsResource extends RowTagsResource
{
    /** @return array{tags: list<list<int>|string>, id: int, extra: bool} */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'extra' => true];
    }
}
