<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrapPicks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/**
 * Wraps a status or a role under a `@return` naming both bare, alone and beside a value only the `@return` types.
 *
 * @property-read WrapPicks $resource
 */
final class TwoWrapsDeclaredResource extends JsonResource
{
    /** @return array{subject: Status|Role, gap: Status|Role} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->role),
            'gap' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d ? EnumResource::make($this->resource->role) : cache('x')),
        ];
    }
}
