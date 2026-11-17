<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/**
 * A test-only resource whose class and toArray() each #[TsCasts] a key as a case value beside its enum's alias, and an
 * uncast key the same body sends (GH-166).
 *
 * @property-read MediaType $resource
 */
#[TsCasts(['k' => "'image' | MediaTypeType"])]
class CastRedundantArmsResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['m' => "'image' | MediaTypeType"])]
    public function toArray(Request $request): array
    {
        $c = $request->boolean('c');

        return [
            'k' => $c ? MediaType::Image->value : $this->resource->value,
            'm' => $c ? MediaType::Image->value : $this->resource->value,
            'free' => $c ? MediaType::Image->value : $this->resource->value,
        ];
    }
}
