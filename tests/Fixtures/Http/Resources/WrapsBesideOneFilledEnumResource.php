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
 * Wraps a status or a role, else sends rows whose status only the `@return` types bare (GH-154).
 *
 * @property-read WrapPicks $resource
 */
final class WrapsBesideOneFilledEnumResource extends JsonResource
{
    /** @return array{subject: Status|Role|list<array{status: Status}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d
                    ? EnumResource::make($this->resource->role)
                    : array_map(fn ($r) => ['status' => cache('status')], $this->resource->rows)),
        ];
    }
}
