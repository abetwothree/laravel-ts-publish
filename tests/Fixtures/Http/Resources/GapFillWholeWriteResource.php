<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Returns a local an undocumented helper writes whole, which its own `@return` types (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillWholeWriteResource extends JsonResource
{
    /** @return array{tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        $data = $this->tagged();

        return $data;
    }

    /** Rows holding a value no rule types or null. */
    private function tagged(): array
    {
        return ['tags' => array_map(fn (GapFillRow $r) => $r->ok ? cache('k') : null, $this->resource->rows)];
    }
}
