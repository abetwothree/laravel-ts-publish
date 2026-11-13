<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\ReadingSheet;

/**
 * A resource declared through `@mixin` to hold a reading sheet, whose docblock list has a `mixed` member the
 * `toArray()` `@return` docblock types (GH-154).
 *
 * @mixin ReadingSheet
 */
class MixinReadingsResource extends JsonResource
{
    /**
     * @return array{entries: list<array{label: string|null}>}
     */
    public function toArray(Request $request): array
    {
        return ['entries' => $this->entries];
    }
}
