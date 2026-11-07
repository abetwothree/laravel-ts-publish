<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sends shapes under `@return` members that list shapes, or offer a map or a list (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewListedShapesResource extends JsonResource
{
    /**
     * @return array{nullable: list<array{m: list<array{a: int}|null>}>, top: array<string, int>|list<int>,
     *     either: list<array{m: list<array{a: int}|array{b: string}>}>,
     *     recordOrList: list<array{m: array<string, int>|list<int>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'nullable' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'either' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'recordOrList' => array_map(fn ($r) => ['m' => ['a' => cache('a')]], $this->resource->rows),
            'top' => ['a' => cache('a'), 'b' => $this->resource->n],
        ];
    }
}
