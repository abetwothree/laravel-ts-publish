<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One local under a typed key and under a key the `@return` does not name, which reads an offset of it (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillConsumedUntypedResource extends JsonResource
{
    /** @return array{a: list<int>|null} */
    public function toArray(Request $request): array
    {
        $x = $this->resource->rows !== [] ? cache('l') : null;

        return ['a' => $x, 'b' => $x[0] ?? 0];
    }
}
