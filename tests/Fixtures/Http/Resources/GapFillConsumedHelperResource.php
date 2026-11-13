<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A spread helper's local under a key the caller types and under one reading an offset of it (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillConsumedHelperResource extends JsonResource
{
    /** @return array{a: list<int>|null, n: int} */
    public function toArray(Request $request): array
    {
        return [...$this->part(), 'n' => 1];
    }

    /** A key the caller types and one that reads an offset of the same local. */
    private function part(): array
    {
        $x = $this->resource->rows !== [] ? cache('l') : null;

        return ['a' => $x, 'b' => $x[0] ?? 0];
    }
}
