<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps rows to int keys written out of order with values no rule types, which the resource re-indexes (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewOutOfOrderPairsResource extends JsonResource
{
    /**
     * @return array{pairs: list<array{0: int, 1: string}>, maybe: list<array{0: int, 1: string}|null>}
     */
    public function toArray(Request $request): array
    {
        return [
            'pairs' => array_map(fn ($r) => [1 => cache('x'), 0 => cache('y')], $this->resource->rows),
            'maybe' => array_map(fn ($r) => $r->note !== null ? [1 => cache('x'), 0 => cache('y')] : null, $this->resource->rows),
        ];
    }
}
