<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A value no rule types beside null under a `mixed` member and under a key the `@return` does not name (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillUndeclaredResource extends JsonResource
{
    /** @return array{rows: list<array{t: mixed, id: int}>} */
    public function toArray(Request $request): array
    {
        return [
            'rows' => array_map(fn (GapFillRow $r) => ['t' => $r->ok ? cache('t') : null, 'id' => $r->id], $this->resource->rows),
            'loose' => array_map(fn (GapFillRow $r) => $r->ok ? cache('l') : null, $this->resource->rows),
        ];
    }
}
