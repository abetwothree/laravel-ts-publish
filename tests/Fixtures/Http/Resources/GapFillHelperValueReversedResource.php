<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One undocumented helper read under a key the `@return` does not type, then under one it does (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillHelperValueReversedResource extends JsonResource
{
    /** @return array{typed: list<list<int>|string>} */
    public function toArray(Request $request): array
    {
        return ['untyped' => $this->tagsOf(), 'typed' => $this->tagsOf()];
    }

    /** Rows holding a list, or a value no rule types. */
    private function tagsOf(): array
    {
        return array_map(fn (GapFillRow $r) => $r->ok ? [1, 2] : cache('k'), $this->resource->rows);
    }
}
