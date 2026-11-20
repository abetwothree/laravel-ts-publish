<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapRow;

/**
 * A wrap, a bare read of the same enum and rows the `@return` fills (GH-160). The read sends the enum's value.
 *
 * @property-read EnumWrapBox $resource
 */
class DeclaredWrapRowsResource extends JsonResource
{
    /**
     * @return array{subject: Status|list<array{s: Status}>}
     */
    public function toArray(Request $request): array
    {
        return ['subject' => $this->resource->c
            ? EnumResource::make($this->resource->status)
            : ($this->resource->d
                ? $this->resource->status
                : array_map(fn (EnumWrapRow $r) => ['s' => cache('s')], $this->resource->rows))];
    }
}
