<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing a key, then spreading a value that is one of two closed arrays, each writing it again.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldUnionValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ternary' => ['by' => $this->resource->author, ...($request->boolean('picked') ? ['by' => $this->resource->title] : ['by' => 1])],
            'ternary_partial' => [
                'by' => $this->resource->author,
                ...($request->boolean('picked') ? ['by' => $this->resource->title, 'x' => 1] : ['by' => 2]),
            ],
            'array_merge' => ['by' => $this->resource->author, ...array_merge(['by' => $this->resource->title], [])],
        ];
    }
}
