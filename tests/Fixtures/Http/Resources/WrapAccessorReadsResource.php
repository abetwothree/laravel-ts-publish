<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrapAccessorGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads its model's accessors that return an EnumResource wrap, alone or in a shape, directly, through whenAppended()
 * and under a key (GH-160).
 *
 * @mixin WrapAccessorGrade
 */
class WrapAccessorReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'shape' => $this->shape,
            'wrap' => $this->wrap,
            'appended' => $this->whenAppended('wrap'),
            'nested' => ['s' => $this->shape],
        ];
    }
}
