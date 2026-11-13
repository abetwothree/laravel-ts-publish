<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A typed key reading an offset of a local no other key holds, so no merge fills its gap (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillConsumedUnfilledResource extends JsonResource
{
    /** @return array{b: int} */
    public function toArray(Request $request): array
    {
        $x = $this->resource->rows !== [] ? cache('l') : null;

        return ['b' => $x[0] ?? 0];
    }
}
