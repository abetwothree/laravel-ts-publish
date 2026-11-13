<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** A resource that redeclares `$resource` as a generic collection or an enum, and reads the enum's value. */
final class GenericOrEnumVarResource extends JsonResource
{
    /** @var Collection<int, string>|Grade */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['value' => $this->resource->value];
    }
}
