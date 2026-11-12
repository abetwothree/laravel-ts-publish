<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A typed key written from an untyped key of the same local, so one gap is filled under one key and left in the other
 * (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillAliasResource extends JsonResource
{
    /** @return array{tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        $data = ['raw' => array_map(fn (GapFillRow $r) => $r->ok ? cache('k') : null, $this->resource->rows)];
        $data['tags'] = $data['raw'];

        return $data;
    }
}
