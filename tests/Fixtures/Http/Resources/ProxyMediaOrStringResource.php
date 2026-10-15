<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/** A resource that redeclares `$resource` as an enum or a string, so only the enum can answer a method call. */
final class ProxyMediaOrStringResource extends JsonResource
{
    /** @var MediaType|string */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'max' => $this->maxSizeMb(),
            'max_via_resource' => $this->resource->maxSizeMb(),
            'prefixes' => $this->resource::mimePrefixes(),
        ];
    }
}
