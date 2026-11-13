<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A helper whose own `@return` fills its gap, spread in a literal under a key no `@return` types (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillNestedHelperResource extends JsonResource
{
    /** The helper's keys under one undocumented key. */
    public function toArray(Request $request): array
    {
        return ['meta' => [...$this->part()]];
    }

    /**
     * A key its own `@return` types, whose value leaves a gap.
     *
     * @return array{a: list<int>|null}
     */
    private function part(): array
    {
        return ['a' => $this->resource->rows !== [] ? cache('l') : null];
    }
}
