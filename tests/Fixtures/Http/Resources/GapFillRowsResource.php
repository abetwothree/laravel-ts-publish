<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keys whose `@return` member fills the arm a union left out beside a typed arm or a placeholder (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillRowsResource extends JsonResource
{
    /**
     * @return array{top: array{s: string|null, t: list<int>|null},
     *     mapped: list<array{s: string|null, t: list<int>|null}>,
     *     collected: list<array{s: string|null, t: list<int>|null}>, empty: list<int>}
     */
    public function toArray(Request $request): array
    {
        return [
            'top' => ['s' => (string) $this->resource->loose, 't' => $request->has('x') ? cache('x') : null],
            'mapped' => array_map(fn ($r) => ['s' => (string) $r, 't' => $request->has('x') ? cache('x') : null], $this->resource->plain),
            'collected' => collect([1, 2])->map(fn (int $n) => ['s' => (string) $n, 't' => $n > 1 ? cache('x') : null])->all(),
            'empty' => $request->has('x') ? cache('x') : [],
        ];
    }
}
