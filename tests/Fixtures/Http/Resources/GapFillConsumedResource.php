<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One local under two typed keys, the second reading an offset of it, so one gap is filled and consumed (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillConsumedResource extends JsonResource
{
    /** @return array{a: list<int>|null, b: int} */
    public function toArray(Request $request): array
    {
        $x = $this->resource->rows !== [] ? cache('l') : null;

        return ['a' => $x, 'b' => $x[0] ?? 0];
    }
}
