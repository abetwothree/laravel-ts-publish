<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A documented helper's argument under the key it types and only decided on under another, spread nested (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillNestedDecisionArgumentResource extends JsonResource
{
    /** The helper's keys under one undocumented key. */
    public function toArray(Request $request): array
    {
        return ['meta' => [...$this->part($this->resource->rows !== [] ? cache('l') : null)]];
    }

    /**
     * The argument under the key its own `@return` types and a number decided on it.
     *
     * @return array{a: list<int>|null}
     */
    private function part($x): array
    {
        return ['a' => $x, 'b' => $x ? 1 : 0];
    }
}
