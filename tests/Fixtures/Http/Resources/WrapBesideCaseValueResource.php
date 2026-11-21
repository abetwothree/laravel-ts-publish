<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrapPicks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/**
 * Wraps a media kind beside a case's value or the kind's own value, under one key or as another arm, and beside the
 * case's value alone (GH-160).
 *
 * @property-read WrapPicks $resource
 */
final class WrapBesideCaseValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pair' => [
                'w' => EnumResource::make($this->resource->kind),
                'v' => $this->resource->c ? MediaType::Image->value : $this->resource->kind->value,
            ],
            'wrap_or_pair' => $this->resource->c
                ? EnumResource::make($this->resource->kind)
                : ['v' => $this->resource->d ? MediaType::Image->value : $this->resource->kind->value],
            'wrap_or_case' => $this->resource->c ? EnumResource::make($this->resource->kind) : MediaType::Image->value,
        ];
    }
}
