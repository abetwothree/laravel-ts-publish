<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Preserves its keys, so int keys written out of order reach the client as an object (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewKeptPairsResource extends JsonResource
{
    /** @var bool */
    public $preserveKeys = true;

    /**
     * @return array{pairs: list<array{0: int, 1: string}>, pair: array{0: int, 1: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'pairs' => array_map(fn ($r) => [1 => cache('x'), 0 => cache('y')], $this->resource->rows),
            'pair' => [1 => cache('x'), 0 => cache('y')],
        ];
    }
}
