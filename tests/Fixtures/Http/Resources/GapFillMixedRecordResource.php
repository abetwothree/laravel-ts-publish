<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A value no rule types beside null under an `array<string, mixed>` docblock (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillMixedRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['rows' => array_map(fn (GapFillRow $r) => ['t' => $r->ok ? cache('t') : null], $this->resource->rows)];
    }
}
