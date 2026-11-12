<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A documented helper's argument under the key it types and under one reading an offset of it (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillConsumedArgumentResource extends JsonResource
{
    /** The helper's keys, under no `@return`. */
    public function toArray(Request $request): array
    {
        return [...$this->part($this->resource->rows !== [] ? cache('l') : null)];
    }

    /**
     * The argument under the key its own `@return` types and under one reading an offset of it.
     *
     * @return array{a: list<int>|null}
     */
    private function part($x): array
    {
        return ['a' => $x, 'b' => $x[0] ?? 0];
    }
}
