<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\WrapAccessorGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keeps its model's accessors that return an EnumResource wrap through its own only() (GH-160).
 *
 * @mixin WrapAccessorGrade
 */
class WrapAccessorOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['shape', 'wrap']);
    }
}
